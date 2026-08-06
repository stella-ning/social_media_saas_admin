"""
SocialAI 社媒自动登录 / 评论引流辅助服务
FastAPI + Playwright

接口：
  POST /api/auto-login
  POST /api/check-cookie
  POST /api/check-homepage   用户主页核验（随机延时）
  POST /api/reply-comment    真人模拟评论回复（默认 dry-run）
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.schemas import AutoLoginRequest, CheckCookieRequest, ApiResponse
from app.login_runner import run_auto_login, run_check_cookie
from app.comment_ops import run_check_homepage, run_reply_comment

app = FastAPI(title="SocialAI Login Service", version="1.1.0")

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
    result = await run_auto_login(body)
    return result


@app.post("/api/check-cookie", response_model=ApiResponse)
async def check_cookie(body: CheckCookieRequest):
    result = await run_check_cookie(body)
    return result


@app.post("/api/check-homepage")
async def check_homepage(body: dict):
    """访问用户主页前随机休眠，返回 real_consumer / marketing / unknown"""
    return await run_check_homepage(body)


@app.post("/api/reply-comment")
async def reply_comment(body: dict):
    """模拟滑动/停留后发送评论；dry_run 默认 true 便于联调"""
    return await run_reply_comment(body)
