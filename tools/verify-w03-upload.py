#!/usr/bin/env python3
"""W03: actual PHP multipart + real Bitrix/Symfony, no DB or notification senders.

The disposable PHP server has --network none, no host ports, read-only inputs.
Only docker exec curl talks to its loopback. All bytes are synthetic fixtures.
"""
import argparse
import json
from pathlib import Path
import subprocess
import time
import uuid

ROOT = Path(__file__).resolve().parents[1]


def check(condition, message):
    if not condition:
        raise RuntimeError(message)


def docker(*args, allow_fail=False):
    result = subprocess.run(['docker', *args], capture_output=True, text=True)
    if not allow_fail:
        check(result.returncode == 0, result.stderr)
    return result.stdout


def multipart(fields, files):
    boundary = 'w03-' + uuid.uuid4().hex
    chunks = []
    for name, value in fields.items():
        chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n').encode())
    for name, filename, mime, content in files:
        chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode() + content + b'\r\n')
    chunks.append(f'--{boundary}--\r\n'.encode())
    return b''.join(chunks), 'multipart/form-data; boundary=' + boundary


def request(container, path, body, content_type):
    result = subprocess.run(['docker', 'exec', '-i', container, 'curl', '--silent', '--show-error', '--max-time', '15',
        '--request', 'POST', '--header', 'Expect:', '--header', 'Content-Type: ' + content_type,
        '--data-binary', '@-', '--write-out', '\n%{http_code}', 'http://127.0.0.1:8000' + path], input=body, capture_output=True)
    check(result.returncode == 0, result.stderr.decode(errors='replace'))
    raw, status = result.stdout.rsplit(b'\n', 1)
    return int(status), json.loads(raw)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--api-root', type=Path, default=ROOT / 'api')
    parser.add_argument('--kernel', type=Path, required=True)
    parser.add_argument('--vendor', type=Path, required=True)
    parser.add_argument('--image', default='rabit-api-php-cli:20260911-074507')
    args = parser.parse_args()
    for path in [args.api_root / 'tools/w03/upload-fixture.php', args.kernel / 'modules/main/lib/loader.php', args.vendor / 'autoload.php']:
        check(path.is_file(), 'Fixture input missing: ' + str(path))
    container = 'rabit-w03-http-' + uuid.uuid4().hex[:10]
    checked = []
    try:
        docker('run', '-d', '--name', container, '--label', 'rabit.wave=W03', '--network', 'none', '--read-only',
               '--tmpfs', '/tmp:rw,nosuid,nodev,size=64m', '--cap-drop', 'ALL',
               '-v', str(args.api_root.resolve()) + ':/app:ro', '-v', str(args.vendor.resolve()) + ':/app/vendor:ro',
               '-v', str(args.kernel.resolve()) + ':/kernel:ro', '-w', '/app', '--entrypoint', 'php', args.image,
               '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'upload_max_filesize=20M', '-d', 'post_max_size=22M',
               '-S', '127.0.0.1:8000', 'tools/w03/upload-fixture.php')
        for attempt in range(30):
            try:
                status, _ = request(container, '/invalid-file-fixture', b'', 'text/plain')
                if status == 400:
                    break
            except (RuntimeError, ValueError):
                pass
            time.sleep(0.1)
        else:
            raise RuntimeError('Isolated PHP server not ready; ' + docker('logs', container, allow_fail=True))
        text_file = ('file', 'fixture.txt', 'text/plain', b'Fixture upload\n')
        samples = [
            ('text', {'moduleId': 'rebit.share'}, [text_file], 200),
            ('pdf', {'moduleId': 'rebit.share'}, [('file', 'fixture.pdf', 'application/pdf', b'%PDF-1.4\nfixture\n%%EOF\n')], 200),
            ('server-metadata', {'moduleId': 'rebit.share', 'name': 'forged.php', 'size': '1', 'type': 'application/x-php', 'tmpName': '/private/forged'}, [text_file], 200),
            ('untrusted-client-mime', {'moduleId': 'rebit.share'}, [('file', 'fixture.txt', 'application/x-php', b'Actual text\n')], 200),
            ('basename-only', {'moduleId': 'rebit.share'}, [('file', '../../fixture.txt', 'text/plain', b'Actual text\n')], 200),
            ('missing-file', {'moduleId': 'rebit.share'}, [], 400),
            ('multi-file', {'moduleId': 'rebit.share'}, [('file[]', 'one.txt', 'text/plain', b'one'), ('file[]', 'two.txt', 'text/plain', b'two')], 400),
            ('extra-file-field', {'moduleId': 'rebit.share'}, [text_file, ('other', 'two.txt', 'text/plain', b'two')], 400),
            ('zero-size', {'moduleId': 'rebit.share'}, [('file', 'empty.txt', 'text/plain', b'')], 400),
            ('missing-module', {}, [text_file], 400),
            ('array-module', {'moduleId[]': 'rebit.share'}, [text_file], 400),
            ('path-module', {'moduleId': '../private'}, [text_file], 400),
            ('long-module', {'moduleId': 'a' * 51}, [text_file], 400),
            ('forbidden-content', {'moduleId': 'rebit.share'}, [('file', 'payload.php', 'text/plain', b'<?php echo 1;\n')], 400),
            ('extension-mismatch', {'moduleId': 'rebit.share'}, [('file', 'payload.php', 'text/plain', b'Just text\n')], 400),
            ('maximum-size', {'moduleId': 'rebit.share'}, [('file', 'large.pdf', 'application/pdf', b'%PDF-1.4\n' + b'a' * (15 * 1024 * 1024 - 9))], 200),
            ('oversize', {'moduleId': 'rebit.share'}, [('file', 'large.txt', 'text/plain', b'a' * (15 * 1024 * 1024 + 1))], 400),
        ]
        for name, fields, files, expected in samples:
            body, content_type = multipart(fields, files)
            status, response = request(container, '/upload-fixture', body, content_type)
            check(status == expected, f'{name}: {status} {response}')
            if expected == 200:
                data = response['data']
                check(data['uploadedByPhp'] is True and data['size'] == len(files[0][3]), name + ': actual upload missing')
                check(data['moduleId'] == 'rebit.share', name + ': module changed')
                check(data['type'] in ['text/plain', 'application/pdf'], name + ': client MIME trusted')
                check(data['name'] == files[0][1].rsplit('/', 1)[-1], name + ': client metadata overrode upload')
            else:
                check(response['data'] == [] and isinstance(response['error']['message'], str), name + ': error envelope changed')
                check('debug' not in response['error'], name + ': debug leaked')
                check('/private/' not in json.dumps(response), name + ': path leaked')
            checked.append(name)
        for debug in ['0', '1']:
            status, response = request(container, '/invalid-file-fixture?debug=' + debug, b'', 'text/plain')
            check(status == 400 and response == {'data': [], 'error': {'message': 'Некорректный файл или параметры загрузки.'}}, 'InvalidFileException disclosed internals')
        for fields in [{'moduleId': '../private'}, {'moduleId': 'rebit.share', 'extra': 'w03-secret-extra'}]:
            body, content_type = multipart(fields, [text_file])
            status, response = request(container, '/upload-fixture?debug=1', body, content_type)
            check(status == 400 and 'debug' not in response['error'], 'Validation error debug trace leaked')
            check('w03-secret-extra' not in json.dumps(response), 'Validation input leaked')
        checked.append('invalid-file-and-parameters-safe-including-debug')
        fields = {'name': 'W03 Test', 'phone': '+70000000000', 'description': 'Synthetic fixture only', 'source': 'w03'}
        for files in [[], [text_file]]:
            body, content_type = multipart(fields, files)
            status, response = request(container, '/lead-fixture', body, content_type)
            check(status == 200 and response['data'] == {'name': 'W03 Test', 'source': 'w03', 'attachment': bool(files)}, 'Lead scalar DTO or optional file changed: ' + str(response))
        checked.append('lead-scalar-and-optional-file-no-delivery')
        print(json.dumps({'status': 'PASS', 'scenarios': checked, 'realPhpMultipart': True, 'realBitrixClasses': True,
                          'realJsonExceptionResponse': True, 'externalNetwork': False, 'deliveries': 0, 'databaseUsed': False}, indent=2))
    finally:
        docker('rm', '-f', container, allow_fail=True)


if __name__ == '__main__':
    main()
