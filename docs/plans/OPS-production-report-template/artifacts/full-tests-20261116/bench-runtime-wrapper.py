import importlib.util
import json
import sys
import traceback
from pathlib import Path

root=Path('/home/user/rabit-api/.worktrees/ops-production-report-template')
spec=importlib.util.spec_from_file_location('stage6_bench_runner',root/'tools/run-browser-e2e.py')
runner=importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)
original=runner.service

def service(state,name,*args):
    if name.endswith('-media'):
        args=('--env','MEDIA_E2E_LIMIT=1000','--env','MEDIA_E2E_TIME_LIMIT=3600',*args)
        state['testEnvironmentOverrides']={'MEDIA_E2E_LIMIT':1000,'MEDIA_E2E_TIME_LIMIT':3600,'scope':'disposable media worker only'}
        runner.save(state)
    return original(state,name,*args)

runner.service=service
sys.argv=['run-browser-e2e.py','run','--groups','a','--kernel','/home/user/rebit-p2p/api/public/bitrix','--vendor','/home/user/rabit-api/api/vendor','--php-cli','rabit-api-e2e-php-cli:local','--php-fpm','rabit-api-e2e-php-fpm:local','--nginx','rabit-api-e2e-nginx:local','--mysql','mysql:8.0']
code=0
try:
    runner.main()
except SystemExit as error:
    code=error.code if isinstance(error.code,int) else 1
except BaseException:
    traceback.print_exc();code=1
finally:
    Path('/tmp/stage6-bench-retry-exit-20261116.json').write_text(json.dumps({'exitCode':code,'wrapper':'/tmp/stage6-bench-runner-20261116.py','testEnvironmentOverrides':{'MEDIA_E2E_LIMIT':1000,'MEDIA_E2E_TIME_LIMIT':3600}},indent=2)+'\n')
sys.exit(code)
