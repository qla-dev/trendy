"""Check release command selection without running deployment commands."""
import json
import shlex
from pathlib import Path
import subprocess

source = (Path(__file__).resolve().parents[2] / 'public/redeploy.php').read_text()
selection = source[source.index('$npmCommand ='):source.index('$startedAt =')]
for flags in [{'frontend_only': '1'}, {'frontend_only': '1', 'ponude_only': '1'}]:
    setup = '$npm="npm"; $baseDir="/tmp/trendy"; $write=static function($message){}; $_GET=json_decode(' + repr(json.dumps(flags)) + ',true);'
    result = subprocess.run(['php', '-r', setup + selection + 'echo json_encode($commands);'], capture_output=True, text=True, check=True)
    commands = [step['command'] for step in json.loads(result.stdout)]
    assert [shlex.split(command) for command in commands] == [
        ['git', 'pull', '--ff-only', 'origin', 'main'],
        ['npm', 'ci', '--no-audit', '--no-fund'],
        ['npm', 'run', 'production'],
    ], commands
    print('PASS: frontend_only selects only pull, npm ci and production build')
