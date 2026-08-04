from typing import Any, Optional

from pydantic import BaseModel, Field


class AutoLoginRequest(BaseModel):
    platform: str = Field(..., description="xiaohongshu / douyin / channels")
    proxy_server_addr: str = Field(..., description="http://ip:port 或 socks5://ip:port")
    account: str
    password: str
    verify_code: Optional[str] = None
    user_agent: str


class CheckCookieRequest(BaseModel):
    platform: str
    proxy_server_addr: str
    cookies: list[dict[str, Any]] = Field(default_factory=list)
    user_agent: str


class ApiResponse(BaseModel):
    code: int = 200
    msg: str = "ok"
    success: bool = True
    captcha: bool = False
    data: dict[str, Any] = Field(default_factory=dict)
