"""
Playwright 浏览器生命周期：
- 一账号一上下文
- 强制挂载传入代理
- 固定 UA + 1920x1080
- 结束后关闭浏览器释放资源
"""

from __future__ import annotations

import logging
from contextlib import asynccontextmanager
from typing import Any, AsyncIterator

from playwright.async_api import async_playwright, Browser, BrowserContext, Page

from app.platforms import (
    PLATFORM_HANDLERS,
    CHECK_URLS,
    CaptchaDetected,
    LoginFailed,
    cookies_look_logged_in,
    normalize_cookies,
)
from app.humanize import human_delay
from app.schemas import AutoLoginRequest, CheckCookieRequest, ApiResponse

logger = logging.getLogger("socialai.login")
logging.basicConfig(level=logging.INFO)


def _safe_log_account(account: str) -> str:
    """日志仅保留哈希前缀，禁止明文"""
    import hashlib

    return hashlib.sha256(account.encode("utf-8")).hexdigest()[:12]


def _parse_viewport() -> dict[str, int]:
    return {"width": 1920, "height": 1080}


@asynccontextmanager
async def browser_context(
    proxy_server_addr: str,
    user_agent: str,
) -> AsyncIterator[tuple[Browser, BrowserContext, Page]]:
    playwright = await async_playwright().start()
    browser = await playwright.chromium.launch(
        headless=True,
        proxy={"server": proxy_server_addr},
        args=["--disable-blink-features=AutomationControlled"],
    )
    context = await browser.new_context(
        user_agent=user_agent,
        viewport=_parse_viewport(),
        locale="zh-CN",
    )
    page = await context.new_page()
    try:
        yield browser, context, page
    finally:
        try:
            await context.close()
        except Exception:
            pass
        try:
            await browser.close()
        except Exception:
            pass
        try:
            await playwright.stop()
        except Exception:
            pass


async def run_auto_login(body: AutoLoginRequest) -> ApiResponse:
    platform = body.platform.strip().lower()
    handler = PLATFORM_HANDLERS.get(platform)
    if not handler:
        return ApiResponse(code=400, success=False, msg=f"不支持的平台: {platform}")

    logger.info(
        "auto_login.start platform=%s proxy=%s account_hash=%s",
        platform,
        body.proxy_server_addr,
        _safe_log_account(body.account),
    )

    try:
        async with browser_context(body.proxy_server_addr, body.user_agent) as (_b, context, page):
            await handler(page, body.account, body.password, body.verify_code)
            await human_delay(800, 1600)
            cookies = await context.cookies()

            if not cookies_look_logged_in(cookies, platform):
                # 再等一轮，部分站点登录后异步写 cookie
                await human_delay(1500, 2500)
                cookies = await context.cookies()

            if not cookies:
                return ApiResponse(code=400, success=False, msg="登录失败：未获取到 Cookie")

            if not cookies_look_logged_in(cookies, platform):
                return ApiResponse(
                    code=400,
                    success=False,
                    msg="登录失败：会话 Cookie 未就绪（可能密码错误或需验证码）",
                )

            logger.info("auto_login.success platform=%s cookie_count=%s", platform, len(cookies))
            return ApiResponse(
                code=200,
                success=True,
                msg="登录成功",
                data={
                    "cookies": cookies,
                    "user_agent": body.user_agent,
                },
            )
    except CaptchaDetected as e:
        logger.warning("auto_login.captcha platform=%s", platform)
        return ApiResponse(code=400, success=False, msg=str(e), captcha=True)
    except LoginFailed as e:
        logger.warning("auto_login.failed platform=%s msg=%s", platform, str(e))
        return ApiResponse(code=400, success=False, msg=str(e), captcha=e.captcha)
    except Exception as e:
        logger.exception("auto_login.error platform=%s", platform)
        return ApiResponse(code=500, success=False, msg=f"登录异常: {type(e).__name__}")


async def run_check_cookie(body: CheckCookieRequest) -> ApiResponse:
    platform = body.platform.strip().lower()
    url = CHECK_URLS.get(platform)
    if not url:
        return ApiResponse(code=400, success=False, msg=f"不支持的平台: {platform}", data={"valid": False})

    cookies = normalize_cookies(body.cookies)
    if not cookies:
        return ApiResponse(code=200, success=True, msg="无 Cookie", data={"valid": False})

    try:
        async with browser_context(body.proxy_server_addr, body.user_agent) as (_b, context, page):
            await context.add_cookies(cookies)
            await page.goto(url, wait_until="domcontentloaded", timeout=45000)
            await human_delay(800, 1500)
            current = await context.cookies()
            valid = cookies_look_logged_in(current, platform)
            # 页面若跳转到登录页也判失效
            if "login" in (page.url or "").lower():
                valid = False
            return ApiResponse(
                code=200,
                success=True,
                msg="会话有效" if valid else "会话失效",
                data={"valid": valid},
            )
    except Exception as e:
        logger.exception("check_cookie.error platform=%s", platform)
        return ApiResponse(
            code=500,
            success=False,
            msg=f"检测异常: {type(e).__name__}",
            data={"valid": False},
        )
