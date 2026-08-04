"""
SocialAI 社媒自动登录服务
FastAPI + Playwright

启动：
  cd python-login-service
  pip install -r requirements.txt
  playwright install chromium
  uvicorn app.main:app --host 0.0.0.0 --port 8100 --reload

接口：
  POST /api/auto-login   挂载代理 + 固定 UA 自动登录，返回 Cookie JSON
  POST /api/check-cookie 检测会话是否仍有效
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.schemas import AutoLoginRequest, CheckCookieRequest, ApiResponse
from app.login_runner import run_auto_login, run_check_cookie

app = FastAPI(title="SocialAI Login Service", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/api/auto-login", response_model=ApiResponse)
async def auto_login(body: AutoLoginRequest):
    """
    使用指定代理 IP + UA 启动独立 Chromium 上下文完成登录。
    检测到滑块/拼图验证码时返回失败（captcha=true）。
    登录结束后关闭浏览器释放资源。
    """
    result = await run_auto_login(body)
    return result


@app.post("/api/check-cookie", response_model=ApiResponse)
async def check_cookie(body: CheckCookieRequest):
    """检测 Cookie 会话是否有效（同样强制使用传入代理与 UA）"""
    result = await run_check_cookie(body)
    return result
