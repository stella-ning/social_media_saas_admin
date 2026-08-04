"""
各平台网页登录实现（Playwright）
说明：
- 平台页面结构可能变更，选择器集中于此便于后期维护扩展
- 检测到滑块/拼图验证码直接失败返回 captcha=True
- 不在日志中打印账号、密码、Cookie
"""

from __future__ import annotations

import re
from typing import Any

from playwright.async_api import Page, TimeoutError as PlaywrightTimeoutError

from app.humanize import human_delay, type_like_human

CAPTCHA_SELECTORS = [
    ".captcha",
    "#captcha",
    "[class*='captcha']",
    "[class*='geetest']",
    "[class*='slide']",
    "[class*='slider']",
    "text=请完成安全验证",
    "text=拖动滑块",
    "text=拼图",
]


async def detect_captcha(page: Page) -> bool:
    for sel in CAPTCHA_SELECTORS:
        try:
            loc = page.locator(sel).first
            if await loc.count() > 0 and await loc.is_visible():
                return True
        except Exception:
            continue
    return False


class CaptchaDetected(Exception):
    pass


class LoginFailed(Exception):
    def __init__(self, msg: str, captcha: bool = False):
        super().__init__(msg)
        self.captcha = captcha


async def _ensure_no_captcha(page: Page) -> None:
    await human_delay(500, 1000)
    if await detect_captcha(page):
        raise CaptchaDetected("检测到滑块/拼图验证码")


async def login_xiaohongshu(page: Page, account: str, password: str, verify_code: str | None) -> None:
    """小红书网页版登录（兼容手机号密码）"""
    await page.goto("https://www.xiaohongshu.com/", wait_until="domcontentloaded", timeout=60000)
    await human_delay()
    await _ensure_no_captcha(page)

    # 打开登录弹层
    for sel in ["text=登录", "text=登录 / 注册", "[class*='login']"]:
        try:
            btn = page.locator(sel).first
            if await btn.count() and await btn.is_visible():
                await btn.click()
                await human_delay()
                break
        except Exception:
            continue

    # 切到密码登录
    for sel in ["text=密码登录", "text=账号登录", "text=邮箱登录"]:
        try:
            tab = page.locator(sel).first
            if await tab.count() and await tab.is_visible():
                await tab.click()
                await human_delay()
                break
        except Exception:
            continue

    account_input = page.locator("input[type='text'], input[placeholder*='手机'], input[placeholder*='账号']").first
    pwd_input = page.locator("input[type='password']").first
    await type_like_human(account_input, account)
    await type_like_human(pwd_input, password)

    if verify_code:
        code_input = page.locator("input[placeholder*='验证码']").first
        if await code_input.count():
            await type_like_human(code_input, verify_code)

    await _ensure_no_captcha(page)
    submit = page.locator("button:has-text('登录'), button[type='submit']").first
    await submit.click()
    await human_delay(1200, 2500)
    await _ensure_no_captcha(page)

    # 粗略成功判断：出现用户相关入口或离开登录态
    try:
        await page.wait_for_timeout(2000)
    except Exception:
        pass


async def login_douyin(page: Page, account: str, password: str, verify_code: str | None) -> None:
    """抖音网页版登录"""
    await page.goto("https://www.douyin.com/", wait_until="domcontentloaded", timeout=60000)
    await human_delay()
    await _ensure_no_captcha(page)

    for sel in ["text=登录", "[data-e2e='login']", "button:has-text('登录')"]:
        try:
            btn = page.locator(sel).first
            if await btn.count() and await btn.is_visible():
                await btn.click()
                await human_delay()
                break
        except Exception:
            continue

    for sel in ["text=密码登录", "text=账号登录"]:
        try:
            tab = page.locator(sel).first
            if await tab.count() and await tab.is_visible():
                await tab.click()
                await human_delay()
                break
        except Exception:
            continue

    account_input = page.locator("input[placeholder*='手机'], input[placeholder*='账号'], input[type='text']").first
    pwd_input = page.locator("input[type='password']").first
    await type_like_human(account_input, account)
    await type_like_human(pwd_input, password)

    if verify_code:
        code_input = page.locator("input[placeholder*='验证码']").first
        if await code_input.count():
            await type_like_human(code_input, verify_code)

    await _ensure_no_captcha(page)
    submit = page.locator("button:has-text('登录'), button[type='submit']").first
    await submit.click()
    await human_delay(1200, 2500)
    await _ensure_no_captcha(page)


async def login_channels(page: Page, account: str, password: str, verify_code: str | None) -> None:
    """视频号（微信视频号助手）网页登录——页面常需扫码，此处做密码流兼容与验证码拦截"""
    await page.goto("https://channels.weixin.qq.com/login.html", wait_until="domcontentloaded", timeout=60000)
    await human_delay()
    await _ensure_no_captcha(page)

    # 若存在账号密码入口则填写；否则提示需人工/扫码
    account_input = page.locator("input[type='text'], input[placeholder*='账号'], input[placeholder*='手机']").first
    pwd_input = page.locator("input[type='password']").first

    if await account_input.count() and await pwd_input.count():
        await type_like_human(account_input, account)
        await type_like_human(pwd_input, password)
        if verify_code:
            code_input = page.locator("input[placeholder*='验证码']").first
            if await code_input.count():
                await type_like_human(code_input, verify_code)
        await _ensure_no_captcha(page)
        submit = page.locator("button:has-text('登录'), button[type='submit']").first
        if await submit.count():
            await submit.click()
            await human_delay(1200, 2500)
        await _ensure_no_captcha(page)
    else:
        # 视频号助手多为扫码，无法纯密码完成时明确失败
        raise LoginFailed("视频号当前页面需要扫码登录，请人工处理后重试或扩展扫码通道")


PLATFORM_HANDLERS = {
    "xiaohongshu": login_xiaohongshu,
    "xhs": login_xiaohongshu,
    "douyin": login_douyin,
    "channels": login_channels,
    "shipinhao": login_channels,
}


CHECK_URLS = {
    "xiaohongshu": "https://www.xiaohongshu.com/",
    "xhs": "https://www.xiaohongshu.com/",
    "douyin": "https://www.douyin.com/",
    "channels": "https://channels.weixin.qq.com/",
    "shipinhao": "https://channels.weixin.qq.com/",
}


def normalize_cookies(raw: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """转为 Playwright add_cookies 可用结构，并裁剪敏感字段之外的多余键"""
    out: list[dict[str, Any]] = []
    for c in raw:
        item: dict[str, Any] = {
            "name": c.get("name"),
            "value": c.get("value"),
            "domain": c.get("domain") or c.get("host"),
            "path": c.get("path") or "/",
        }
        if not item["name"] or item["value"] is None or not item["domain"]:
            continue
        for k in ("expires", "httpOnly", "secure", "sameSite"):
            if k in c and c[k] is not None:
                item[k] = c[k]
        out.append(item)
    return out


def cookies_look_logged_in(cookies: list[dict[str, Any]], platform: str) -> bool:
    """启发式：关键会话 Cookie 是否存在（平台差异大，可后续扩展）"""
    names = {c.get("name", "") for c in cookies}
    if platform in ("xiaohongshu", "xhs"):
        return bool(names & {"web_session", "customer-sso-sid", "x-user-id-creator.xiaohongshu.com"})
    if platform == "douyin":
        return bool(names & {"sessionid", "sid_tt", "uid_tt", "passport_csrf_token"})
    if platform in ("channels", "shipinhao"):
        return len(cookies) >= 3
    return len(cookies) > 0
