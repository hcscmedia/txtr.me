#!/usr/bin/env python3
import argparse
import json
import os
import shutil
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import http.cookiejar


def opener_with_cookies():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def request_json(opener, url, payload=None, headers=None, timeout=10):
    headers = headers or {}
    if payload is None:
        req = urllib.request.Request(url, headers=headers)
    else:
        req = urllib.request.Request(
            url,
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json", **headers},
        )

    try:
        with opener.open(req, timeout=timeout) as response:
            return response.getcode(), json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as err:
        body = err.read().decode("utf-8") or "{}"
        try:
            data = json.loads(body)
        except Exception:
            data = {"raw": body}
        return err.code, data


def assert_true(condition, message):
    if not condition:
        raise AssertionError(message)


def run_smoke(base_url, admin_password):
    api = base_url.rstrip("/") + "/api.php"

    user_a = opener_with_cookies()
    user_b = opener_with_cookies()
    admin = opener_with_cookies()

    code, posts_a = request_json(user_a, f"{api}?action=get_posts")
    assert_true(code == 200 and posts_a.get("user"), "get_posts user A failed")

    code, posts_b = request_json(user_b, f"{api}?action=get_posts")
    assert_true(code == 200 and posts_b.get("user"), "get_posts user B failed")

    user_a_id = posts_a["user"]["id"]
    user_b_id = posts_b["user"]["id"]

    code, result = request_json(user_a, api, {"action": "set_username", "username": "SmokeUser"})
    assert_true(code == 200 and result.get("success") is True, "set_username failed")

    code, result = request_json(user_a, api, {"action": "follow", "user_id": user_b_id})
    assert_true(code == 200 and result.get("success") is True, "follow failed")

    code, result = request_json(user_a, api, {"action": "unfollow", "user_id": user_b_id})
    assert_true(code == 200 and result.get("success") is True, "unfollow failed")

    post_text = f"Smoke post {int(time.time())}"
    code, result = request_json(user_a, api, {"action": "create", "text": post_text, "link": "", "color": "default"})
    assert_true(code == 200 and result.get("success") is True, "create post failed")
    post_id = result["post"]["id"]

    code, result = request_json(user_a, api, {"action": "view", "id": post_id})
    assert_true(code == 200 and result.get("success") is True, "view failed")

    code, result = request_json(admin, api, {"action": "login", "password": admin_password})
    assert_true(code == 200 and result.get("success") is True, "admin login failed")

    admin_page_url = base_url.rstrip("/") + "/admin.php"
    page = admin.open(urllib.request.Request(admin_page_url), timeout=10).read().decode("utf-8")
    marker = "window.csrfToken = '"
    start = page.find(marker)
    assert_true(start != -1, "csrf token marker not found")
    start += len(marker)
    end = page.find("'", start)
    csrf_token = page[start:end]
    assert_true(bool(csrf_token), "csrf token empty")

    code, result = request_json(
        admin,
        api,
        {"action": "edit_post", "id": post_id, "text": "Edited smoke", "link": "", "color": "blue", "pinned": False},
        headers={"X-CSRF-Token": csrf_token},
    )
    assert_true(code == 200 and result.get("success") is True, "edit_post failed")

    code, result = request_json(admin, f"{api}?action=get_post_detail&id={urllib.parse.quote(post_id)}")
    assert_true(code == 200 and result.get("success") is True, "get_post_detail failed")

    code, result = request_json(
        admin,
        api,
        {"action": "delete", "id": post_id},
        headers={"X-CSRF-Token": csrf_token},
    )
    assert_true(code == 200 and result.get("success") is True, "delete failed")

    code, health = request_json(user_a, base_url.rstrip("/") + "/health.php")
    assert_true(code == 200 and health.get("status") in ["ok", "warn"], "health endpoint failed")


def main():
    parser = argparse.ArgumentParser(description="txtr.me smoke test")
    parser.add_argument("--base-url", default="http://127.0.0.1:8080", help="Base URL der lokalen App")
    parser.add_argument("--admin-password", default="smoke-secret", help="Admin-Passwort für Testlogin")
    parser.add_argument("--start-server", action="store_true", help="Startet lokalen PHP-Server automatisch")
    parser.add_argument("--project-dir", default=os.path.abspath(os.path.join(os.path.dirname(__file__), "..")), help="Projektverzeichnis")
    args = parser.parse_args()

    server = None
    temp_copy_dir = None

    try:
        if args.start_server:
            temp_copy_dir = tempfile.mkdtemp(prefix="txtr-smoke-")
            app_dir = os.path.join(temp_copy_dir, "app")
            shutil.copytree(args.project_dir, app_dir, dirs_exist_ok=True)

            env = os.environ.copy()
            env["ADMIN_PASSWORD"] = args.admin_password

            parsed = urllib.parse.urlparse(args.base_url)
            host = parsed.hostname or "127.0.0.1"
            port = str(parsed.port or 8080)

            server = subprocess.Popen(
                ["php", "-S", f"{host}:{port}"],
                cwd=app_dir,
                env=env,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
            )
            time.sleep(1)

        run_smoke(args.base_url, args.admin_password)
        print("SMOKE_OK")
    finally:
        if server is not None:
            server.terminate()
            try:
                server.wait(timeout=3)
            except Exception:
                server.kill()
        if temp_copy_dir:
            shutil.rmtree(temp_copy_dir, ignore_errors=True)


if __name__ == "__main__":
    main()
