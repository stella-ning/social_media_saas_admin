"""人性化随机延时：800~2000ms，降低平台风控概率"""

from __future__ import annotations

import asyncio
import random


async def human_delay(min_ms: int = 800, max_ms: int = 2000) -> None:
    await asyncio.sleep(random.uniform(min_ms / 1000, max_ms / 1000))


async def type_like_human(locator, text: str) -> None:
    """逐字输入并夹杂短延时"""
    await locator.click()
    await human_delay(400, 900)
    await locator.fill("")
    for ch in text:
        await locator.type(ch, delay=random.randint(60, 180))
    await human_delay()
