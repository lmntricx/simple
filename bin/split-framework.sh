#!/usr/bin/env bash
#
# Publish framework/ to its own repository, with its full history.
#
#   bin/split-framework.sh https://github.com/lmntricx/simple-framework.git [branch]
#
# Safe to run repeatedly: each run pushes the framework's latest commits.
set -euo pipefail

remote="${1:?Usage: bin/split-framework.sh <git-url-of-framework-repo> [branch]}"
branch="${2:-main}"

cd "$(git rev-parse --show-toplevel)"

if [ -n "$(git status --porcelain -- framework)" ]; then
    echo "framework/ has uncommitted changes; commit them first." >&2
    exit 1
fi

echo "Extracting framework/ history..."
git subtree split --prefix=framework -b framework-split

echo "Pushing to $remote ($branch)..."
git push "$remote" "framework-split:$branch"

git branch -D framework-split >/dev/null
echo "Done. Tag a release in that repo (e.g. v2.0.0) and submit it at https://packagist.org."
