"""Isolated Git/PHP checks; never invokes the real deployment URL or npm."""

import json
from pathlib import Path
import subprocess
import tempfile


def run(args, cwd, check=True):
    return subprocess.run(args, cwd=cwd, capture_output=True, text=True, check=check)


def git(cwd, *args):
    return run(["git", *args], cwd).stdout.strip()


source = (Path(__file__).resolve().parents[2] / "public/redeploy.php").read_text()
# Stop after the real Git pull: PHP cannot execute npm's "ci" argument as a file.
source = source.replace("$npmCandidates = [", "$npmCandidates = [PHP_BINARY,", 1)

with tempfile.TemporaryDirectory(prefix="trendy-manifest-checks-") as directory:
    root = Path(directory)
    for scenario in ["changed", "clean", "pull-conflict", "invalid-head"]:
        origin = root / (scenario + "-origin")
        origin.mkdir()
        git(origin, "init", "-b", "main")
        git(origin, "config", "user.email", "deploy-test@example.invalid")
        git(origin, "config", "user.name", "Deployment test")
        (origin / "public").mkdir()
        (origin / "public/redeploy.php").write_text(source)
        original = '{"/app.js":"/app.js?id=old"}\n'
        if scenario == "invalid-head":
            original = "not-json\n"
        (origin / "public/mix-manifest.json").write_text(original)
        (origin / "other.txt").write_text("original\n")
        git(origin, "add", ".")
        git(origin, "commit", "-m", "Initial server version")
        checkout = root / (scenario + "-server")
        run(["git", "clone", str(origin), str(checkout)], root)
        incoming = '{"/app.js":"/app.js?id=new"}\n'
        (origin / "public/mix-manifest.json").write_text(incoming)
        (origin / "other.txt").write_text("incoming change\n")
        git(origin, "commit", "-am", "Incoming main version")
        manifest = checkout / "public/mix-manifest.json"
        generated = '{"/app.js":"/app.js?id=server-build"}\n'
        if scenario != "clean":
            manifest.write_text(generated)
        if scenario == "pull-conflict":
            (checkout / "other.txt").write_text("local change\n")
        result = run(["php", str(checkout / "public/redeploy.php")], checkout, check=False)
        output = result.stdout + result.stderr
        backups = list((checkout / "storage/app/deploy-backups").glob("*.json"))
        if scenario in ["changed", "clean"]:
            assert "Pulling latest Trendy code failed" not in output, output
            assert "Installing frontend dependencies failed" in output, output
            assert git(checkout, "rev-parse", "HEAD") == git(origin, "rev-parse", "HEAD"), output
            assert json.loads(manifest.read_text()) == json.loads(incoming), output
            if scenario == "changed":
                assert len(backups) == 1, output
                assert backups[0].read_text() == generated, output
            else:
                assert not backups, output
        elif scenario == "pull-conflict":
            assert "Pull failed; restored the previous build manifest" in output, output
            assert manifest.read_text() == generated, output
            assert (checkout / "other.txt").read_text() == "local change\n", output
        else:
            assert "Could not read a valid manifest" in output, output
            assert manifest.read_text() == generated, output
            assert not backups, output
        print("PASS:", scenario)

print("All isolated deployment checks passed; no real npm commands were executed.")
