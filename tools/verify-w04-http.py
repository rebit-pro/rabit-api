#!/usr/bin/env python3
"""Actual loopback HTTP -> project nginx -> PHP 8.4 FPM -> real kernel request/mappers.

Only fixture routes exist. No Bitrix prolog, auth, database or delivery is run.
Cookie decoding is disabled; DTO metadata uses a no-storage kernel cache.
Use --api-root for the W03/combined checkout; production files are never mounted.
"""
import argparse
from email.parser import BytesParser
import json
from pathlib import Path
import shutil
import sys
import subprocess
import tempfile
import time
import uuid

ROOT = Path(__file__).resolve().parents[1]


def check(condition, message):
    if not condition:
        raise RuntimeError(message)


def docker(*args, allow_fail=False):
    result = subprocess.run(["docker", *args], text=True, capture_output=True)
    if not allow_fail:
        check(result.returncode == 0, "Docker fixture command failed: " + result.stderr)
    return (result.stdout + (result.stderr if allow_fail else "")).strip()


def request(container, method, path, body=None, headers=None):
    command = ["docker", "exec", "-i", container, "curl", "--silent", "--show-error", "--max-time", "20",
               "--include", "--request", method, "--header", "Expect:"]
    for name, value in (headers or {}).items():
        command.extend(["--header", name + ": " + value])
    if body is not None:
        command.extend(["--data-binary", "@-"])
    command.append("http://127.0.0.1" + path)
    result = subprocess.run(command, input=body, capture_output=True)
    check(result.returncode == 0, "Fixture HTTP request failed: " + result.stderr.decode(errors="replace"))
    head, response_body = result.stdout.split(b"\r\n\r\n", 1)
    status_line, header_bytes = head.split(b"\r\n", 1)
    return int(status_line.split()[1]), BytesParser().parsebytes(header_bytes), response_body


def multipart(fields, files):
    boundary = "w04-" + uuid.uuid4().hex
    chunks = []
    for name, value in fields.items():
        chunks.append(("--" + boundary + '\r\nContent-Disposition: form-data; name="' + name + '"\r\n\r\n' + value + "\r\n").encode())
    for field, filename, content_type, content in files:
        chunks.append(("--" + boundary + '\r\nContent-Disposition: form-data; name="' + field + '"; filename="' + filename + '"\r\nContent-Type: ' + content_type + "\r\n\r\n").encode() + content + b"\r\n")
    chunks.append(("--" + boundary + "--\r\n").encode())
    return b"".join(chunks), "multipart/form-data; boundary=" + boundary


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--api-root", type=Path, default=ROOT / "api")
    parser.add_argument("--kernel", type=Path, required=True)
    parser.add_argument("--vendor", type=Path, required=True)
    parser.add_argument("--skip-multipart", action="store_true", help="Only pre-W03 proxy/JSON gate; report multipart as not-run.")
    args = parser.parse_args()
    for path in [args.api_root / "public/local", args.kernel / "modules/main/lib", args.vendor / "autoload.php"]:
        check(path.exists(), "Required fixture input missing: " + str(path))
    suffix = uuid.uuid4().hex[:10]
    network, fpm, nginx = ("rabit-w04-" + suffix + name for name in ["-net", "-fpm", "-nginx"])
    checked = []
    with tempfile.TemporaryDirectory(prefix="rabit-w04-http-") as temporary:
        fixture = Path(temporary)
        (fixture / "public/bitrix").mkdir(parents=True)
        (fixture / "public/local").mkdir()
        (fixture / "public/local/.settings.php").write_text("<?php return ['cache' => ['value' => ['type' => 'none']]];")
        shutil.copyfile(ROOT / "api/tools/w04/fixture-routing.php", fixture / "public/bitrix/routing_index.php")
        (fixture / "auth.conf").write_text("# Basic Auth is disabled only in this isolated fixture.\n")
        (fixture / "fpm.conf").write_text("[www]\nlisten = 9000\nuser = www-data\ngroup = www-data\npm = static\npm.max_children = 2\nclear_env = no\ncatch_workers_output = yes\n")
        for path in fixture.rglob("*"):
            path.chmod(0o755 if path.is_dir() else 0o644)
        try:
            docker("network", "create", "--internal", network)
            docker("run", "-d", "--name", fpm, "--network", network, "--network-alias", "api-php-fpm",
                   "--read-only", "--tmpfs", "/tmp:rw,nosuid,nodev,size=80m", "--tmpfs", "/usr/local/var:rw,nosuid,nodev,size=8m",
                   "--mount", f"type=bind,source={args.api_root.resolve()},target=/source,readonly",
                   "--mount", f"type=bind,source={args.kernel.resolve()},target=/kernel,readonly",
                   "--mount", f"type=bind,source={args.vendor.resolve()},target=/vendor,readonly",
                   "--mount", f"type=bind,source={fixture / 'public'},target=/app/public,readonly",
                   "--mount", f"type=bind,source={fixture / 'fpm.conf'},target=/usr/local/etc/php-fpm.d/www.conf,readonly",
                   "--entrypoint", "php-fpm", "rabit-api-php-fpm:20260911-074507", "-F",
                   "-d", "display_errors=0", "-d", "log_errors=0",
                   "-d", "upload_max_filesize=20M", "-d", "post_max_size=22M")
            docker("run", "-d", "--name", nginx, "--network", network,
                   "--read-only", "--tmpfs", "/var/cache/nginx:rw,nosuid,nodev,size=48m", "--tmpfs", "/var/run:rw,nosuid,nodev,size=1m",
                   "--mount", f"type=bind,source={ROOT / 'api/docker/common/nginx/conf.d'},target=/etc/nginx/conf.d,readonly",
                   "--mount", f"type=bind,source={fixture / 'auth.conf'},target=/etc/nginx/auth.conf,readonly",
                   "--mount", f"type=bind,source={fixture / 'public'},target=/app/public,readonly",
                   "--entrypoint", "nginx", "rabit-api-nginx:20260911-074507", "-g", "daemon off;")
            base = nginx
            for attempt in range(30):
                try:
                    if request(base, "GET", "/health")[0] == 200:
                        break
                except (OSError, RuntimeError):
                    pass
                time.sleep(0.2)
            else:
                raise RuntimeError("Fixture nginx did not become ready")
            allowed = "https://app.rebit-pro.ru"
            status, headers, body = request(base, "OPTIONS", "/__w04/patch", headers={
                "Origin": allowed, "Access-Control-Request-Method": "PATCH",
                "Access-Control-Request-Headers": "authorization,content-type,idempotency-key,x-order-key,x-unapproved",
            })
            check(status == 204 and body == b"", "PATCH preflight failed")
            check(headers.get("Access-Control-Allow-Origin") == allowed, "Allowed origin rejected")
            check("PATCH" in headers.get("Access-Control-Allow-Methods", ""), "PATCH not allowed")
            allowed_headers = {value.strip().lower() for value in headers["Access-Control-Allow-Headers"].split(",")}
            check(allowed_headers == {"authorization", "content-type", "idempotency-key", "x-order-key"}, "Request headers were reflected")
            checked.append("allowed-origin-patch-preflight-and-header-allowlist")
            status, headers, _ = request(base, "OPTIONS", "/__w04/patch", headers={"Origin": "https://unapproved.invalid", "Access-Control-Request-Method": "PATCH"})
            check(headers.get("Access-Control-Allow-Origin") is None, "Unapproved origin was opened")
            checked.append("unapproved-origin-denied")
            standard = {"Origin": allowed, "Content-Type": "application/json", "Authorization": "Bearer w04-fixture",
                        "Idempotency-Key": "w04-idempotency-fixture", "X-Order-Key": "w04-order-fixture"}
            status, headers, body = request(base, "PATCH", "/__w04/patch", b'{"quantity":2}', standard)
            check(status == 200, "PATCH mapper failed: " + body.decode(errors="replace"))
            result = json.loads(body)
            check(result["result"]["quantity"] == 2 and all(result["headersForwarded"].values()), "PATCH DTO or header forwarding failed")
            checked.append("patch-real-json-mapper")
            for payload in [b'{"quantity":0}', b'{"quantity":[]}', b'{}', b'{"quantity":']:
                status, headers, body = request(base, "PATCH", "/__w04/patch", payload, standard)
                check(status == 400, "Invalid DTO/JSON became success or 500: " + body.decode(errors="replace"))
                check(headers.get("Access-Control-Allow-Origin") == allowed, "CORS disappeared on validation error")
            checked.append("json-and-dto-errors-with-cors")
            if not args.skip_multipart:
                samples = [
                    ("text-upload", {"moduleId": "rebit.share"}, [("file", "fixture.txt", "text/plain", b"Fixture upload\n")], 200),
                    ("pdf-upload", {"moduleId": "rebit.share"}, [("file", "fixture.pdf", "application/pdf", b"%PDF-1.4\nfixture\n%%EOF\n")], 200),
                    ("missing-file", {"moduleId": "rebit.share"}, [], 400),
                    ("multiple-files", {"moduleId": "rebit.share"}, [("file[]", "first.txt", "text/plain", b"one"), ("file[]", "second.txt", "text/plain", b"two")], 400),
                    ("empty-file", {"moduleId": "rebit.share"}, [("file", "empty.txt", "text/plain", b"")], 400),
                    ("invalid-module", {"moduleId": "../private"}, [("file", "fixture.txt", "text/plain", b"fixture")], 400),
                    ("forbidden-type", {"moduleId": "rebit.share"}, [("file", "fixture.php", "application/x-php", b"<?php echo 1;\n")], 400),
                    ("oversize-file", {"moduleId": "rebit.share"}, [("file", "big.txt", "text/plain", b"a" * (15 * 1024 * 1024 + 1))], 400),
                ]
                for name, fields, files, expected in samples:
                    body, content_type = multipart(fields, files)
                    status, headers, response_body = request(base, "POST", "/__w04/upload", body, {"Origin": allowed, "Content-Type": content_type})
                    check(status == expected, name + ": unexpected status/body: " + str(status) + " " + response_body.decode(errors="replace"))
                    check(headers.get("Access-Control-Allow-Origin") == allowed, name + ": CORS header lost")
                    if expected == 200:
                        result = json.loads(response_body)["result"]
                        check(result["uploadedByPhp"] is True and result["size"] == len(files[0][3]), name + ": not a real PHP upload")
                    checked.append(name)
            print(json.dumps({"status": "PASS", "apiSource": str(args.api_root.resolve()), "scenarios": checked,
                              "multipartGate": "not-run" if args.skip_multipart else "passed",
                              "fixtureRoutesOnly": True, "realKernelRequestAndMappers": True, "cookieDecoderBypassed": True,
                              "databaseUsed": False, "deliveriesExecuted": False, "productionUsed": False}, indent=2))
        except Exception:
            for container in [nginx, fpm]:
                print("Fixture logs " + container + ": " + docker("logs", container, allow_fail=True), file=sys.stderr)
            raise
        finally:
            docker("rm", "-f", nginx, allow_fail=True)
            docker("rm", "-f", fpm, allow_fail=True)
            docker("network", "rm", network, allow_fail=True)


if __name__ == "__main__":
    main()
