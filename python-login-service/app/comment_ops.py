"""小红书主页核验 + 评论回复（Playwright 真人行为骨架）"""

from __future__ import annotations

import asyncio
import random
from typing import Any

from app.humanize import human_delay


def classify_homepage(meta: dict[str, Any]) -> str:
    """根据笔记数/粉丝/近期发帖启发式区分真实消费者 vs 营销号"""
    notes = int(meta.get("note_count") or 0)
    fans = int(meta.get("fans_count") or 0)
    posts = meta.get("recent_posts") or []
    biz_hits = 0
    keywords = ("代理", "招商", "拿货", "批发", "微商", "招代理", "日赚")
    for p in posts:
        text = p if isinstance(p, str) else str(p.get("title") or p.get("content") or "")
        for kw in keywords:
            if kw in text:
                biz_hits += 1
                break
    if biz_hits >= 2 or (notes > 80 and fans > 5000) or fans > 20000:
        return "marketing"
    if notes == 0 and fans == 0 and not posts:
        return "unknown"
    return "real_consumer"


async def run_check_homepage(body: dict[str, Any]) -> dict[str, Any]:
    """
    访问用户主页前随机休眠；当前以传入 meta / 启发式为主，
    生产环境可在此用 Playwright 打开 homepage_url 解析 DOM。
    """
    behavior = body.get("behavior") or {}
    delay_min = int(behavior.get("homepage_delay_min_ms") or 800)
    delay_max = int(behavior.get("homepage_delay_max_ms") or 2500)
    await human_delay(delay_min, delay_max)

    meta = {
        "note_count": body.get("note_count") or 0,
        "fans_count": body.get("fans_count") or 0,
        "recent_posts": body.get("recent_posts") or [],
        "source": "python_heuristic",
    }
    result = classify_homepage(meta)
    return {
        "code": 200,
        "msg": "ok",
        "success": True,
        "data": {
            "result": result,
            "meta": meta,
            "homepage_url": body.get("homepage_url") or "",
        },
    }


async def run_reply_comment(body: dict[str, Any]) -> dict[str, Any]:
    """
    模拟真人：随机浏览延迟、滑动、停留后发送评论。
    dry_run=True 时不打开浏览器，仅模拟耗时并返回成功（本地联调）。
    """
    behavior = body.get("behavior") or {}
    dwell_min = int(behavior.get("dwell_min_ms") or 1500)
    dwell_max = int(behavior.get("dwell_max_ms") or 5000)
    scroll_min = int(behavior.get("scroll_min_ms") or 800)
    scroll_max = int(behavior.get("scroll_max_ms") or 2400)
    enable_scroll = bool(behavior.get("enable_scroll", True))

    # 随机浏览 / 停留
    await human_delay(dwell_min, dwell_max)
    if enable_scroll:
        await human_delay(scroll_min, scroll_max)

    interval = int(body.get("reply_interval") or 90)
    # 额外抖动，降低规律性
    await asyncio.sleep(random.uniform(0.5, min(3.0, max(0.5, interval * 0.05))))

    dry_run = bool(body.get("dry_run", True))
    if dry_run:
        return {
            "code": 200,
            "msg": "dry-run ok",
            "success": True,
            "data": {
                "ok": True,
                "message": "dry-run: comment not posted",
                "reply_content": body.get("reply_content"),
                "note_url": body.get("note_url"),
            },
        }

    # TODO: Playwright 打开 note_url，注入 cookies/proxy，滑动后填写评论并发送
    return {
        "code": 501,
        "msg": "live reply not enabled; set dry_run=true or implement Playwright poster",
        "success": False,
        "data": {"ok": False, "message": "not_implemented"},
    }
