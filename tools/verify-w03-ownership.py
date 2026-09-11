#!/usr/bin/env python3
"""Create a fresh internal-only MySQL fixture and verify real Bitrix ownership.

Fixed fixture names are reserved for this verifier. Existing resources are refused.
No host ports or persistent volumes. Never reads project/prod configuration.
"""
import argparse
import json
from pathlib import Path
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
NETWORK = 'rabit-w03-tests'
MYSQL = 'rabit-w03-mysql'


def docker(*args, allow_fail=False):
    result = subprocess.run(['docker', *args], capture_output=True, text=True)
    if not allow_fail and result.returncode:
        raise RuntimeError('Fixture Docker command failed: ' + result.stdout + result.stderr)
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--api-root', type=Path, default=ROOT / 'api')
    parser.add_argument('--kernel', type=Path, required=True)
    parser.add_argument('--vendor', type=Path, required=True)
    parser.add_argument('--image', default='rabit-api-php-cli:20260911-074507')
    parser.add_argument('--mysql-image', default='mysql:8.0')
    args = parser.parse_args()
    for path in [args.api_root / 'tools/verify-w03-ownership.php', args.kernel / 'modules/main/lib/loader.php', args.vendor / 'autoload.php']:
        if not path.is_file():
            raise RuntimeError('Fixture input missing: ' + str(path))
    for kind, name in [('network', NETWORK), ('container', MYSQL)]:
        if docker(kind, 'inspect', name, allow_fail=True).returncode == 0:
            raise RuntimeError('Refusing existing fixture resource: ' + name)
    network_created = database_created = False
    try:
        docker('network', 'create', '--internal', '--label', 'rabit.wave=W03', NETWORK)
        network_created = True
        docker('run', '-d', '--name', MYSQL, '--label', 'rabit.wave=W03', '--network', NETWORK,
               '--tmpfs', '/var/lib/mysql:rw,nosuid,size=384m', '-e', 'MYSQL_ALLOW_EMPTY_PASSWORD=yes',
               '-e', 'MYSQL_ROOT_HOST=%', '-e', 'MYSQL_DATABASE=rabit_w03', args.mysql_image,
               '--skip-log-bin', '--innodb-buffer-pool-size=64M', '--performance-schema=OFF')
        database_created = True
        for attempt in range(90):
            if docker('exec', MYSQL, 'mysqladmin', '--protocol=tcp', '--host=127.0.0.1', 'ping', '--silent', allow_fail=True).returncode == 0:
                break
            time.sleep(0.5)
        else:
            raise RuntimeError('Isolated MySQL did not become ready')
        result = docker('run', '--rm', '--network', NETWORK, '--read-only', '--tmpfs', '/tmp:rw,nosuid,size=64m',
                        '--cap-drop', 'ALL', '-v', str(args.api_root.resolve()) + ':/app:ro',
                        '-v', str(args.vendor.resolve()) + ':/app/vendor:ro', '-v', str(args.kernel.resolve()) + ':/kernel:ro',
                        '-w', '/app', '--entrypoint', '/bin/sh', args.image,
                        '-c', 'php tools/verify-w03-ownership.php write && php tools/verify-w03-ownership.php read-after-restart')
        print(result.stdout, end='')
        print(json.dumps({'status': 'PASS', 'mysqlFixture': 'rabit_w03', 'newPhpProcess': True,
                          'cacheClearAndOfflineFallback': True, 'ownerImmutable': True,
                          'legacyAndForeignDenied': True, 'productionSettingsLoaded': False,
                          'hostPorts': False, 'persistentVolumes': False}))
    finally:
        if database_created:
            docker('rm', '-f', MYSQL, allow_fail=True)
        if network_created:
            docker('network', 'rm', NETWORK, allow_fail=True)


if __name__ == '__main__':
    main()
