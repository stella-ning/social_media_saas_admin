# SocialAI Python 自动登录服务（FastAPI + Playwright）

## 作用
接收 Laravel 发起的自动登录请求，使用**指定代理 IP** 启动 Chromium，挂载账号固定 UA，模拟真人延时完成小红书 / 抖音 / 视频号网页登录，返回 Cookie JSON。

## 安装与启动
```bash
cd python-login-service
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
playwright install chromium
uvicorn app.main:app --host 0.0.0.0 --port 8100 --reload
```

Laravel `.env`：
```
PYTHON_LOGIN_SERVICE_URL=http://127.0.0.1:8100
```

## 接口

### POST /api/auto-login
```json
{
  "platform": "douyin",
  "proxy_server_addr": "http://123.56.78.102:8080",
  "account": "13800001111",
  "password": "***",
  "verify_code": null,
  "user_agent": "Mozilla/5.0 ..."
}
```

成功：
```json
{ "code": 200, "success": true, "msg": "登录成功", "captcha": false, "data": { "cookies": [], "user_agent": "..." } }
```

滑块/拼图：
```json
{ "code": 400, "success": false, "msg": "检测到滑块/拼图验证码", "captcha": true, "data": {} }
```

### POST /api/check-cookie
检测会话是否有效（同样强制代理 + UA）。

## 风控策略
- 点击/输入 800~2000ms 随机延时
- 一浏览器上下文对应单账号，结束即关闭
- 验证码直接失败，由 PHP 置离线

## 后期可扩展
1. 视频号扫码登录通道（二维码轮询）
2. 平台选择器配置热更新
3. 登录结果截图回传（注意脱敏）
4. 与爬虫 Worker 共用同一指纹配置中心
