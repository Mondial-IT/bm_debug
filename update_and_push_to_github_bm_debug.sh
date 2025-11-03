#!/usr/bin/env bash
# Goal: integrate origin while keeping LOCAL content on conflicts, handle unrelated histories, then push.
# This will always store the version from the local directory
# into the repository
# it checks the correct directory, uses the remote path and branch.
# Set the variables before copy-ing it in a directory.
set -euo pipefail

URL="https://github.com/Mondial-IT/bm_debug.git"
DIR="bm_debug"
BRANCH="main"

if [[ ! -d "../${DIR}" ]]; then
  echo "-- USAGE. It will push current directory into the repository preferring local over remote changes. Edit URL, DIR & BRANCH first."
  echo "-- Execute in directory ${DIR}"
  exit 1
fi
cd ../"${DIR}" || exit 1

echo "-- init"
git init >/dev/null

HAS_REMOTE="$(grep "${URL}" .git/config)"
if [ ! "${HAS_REMOTE}" ]; then
  git remote add origin "${URL}" || true
fi
echo "-- remote ${URL}"

echo "-- rev-parse"
git rev-parse --git-dir >/dev/null
echo "-- fetch"
git fetch origin --prune

# Ensure we're on the intended branch
# git checkout -q "${BRANCH}"

echo "-- git add ."
# Stage & checkpoint any unstaged local changes (so the merge has a clean index)
if ! git diff --quiet || ! git diff --cached --quiet; then
  git add -A
  git commit -m "WIP: checkpoint before sync"
fi
echo "-- merge"
# 1) Merge remote while KEEPING LOCAL on conflicts and allowing unrelated histories
#    -s ours => keep current tree; ties both histories together if needed
#    If histories are not unrelated, this still results in a merge that prefers local.
if git ls-remote --exit-code --heads origin "${BRANCH}" >/dev/null 2>&1; then
  git merge --allow-unrelated-histories -s ours --no-edit "origin/${BRANCH}" || true
fi

echo "-- git add ."
# 2) Stage everything (including any auto-resolved merges)
git add -A
if ! git diff --cached --quiet; then
  git commit -m "Sync ${BRANCH}: keep local over origin/${BRANCH}"
fi

echo "-- git push"
# 3) Try a normal push first
set +e
git push origin "${BRANCH}"
PUSH_CODE=$?
set -e

if [ $PUSH_CODE -ne 0 ]; then
echo "-- git push (the hard way)"
  # If push was rejected (e.g., non-fast-forward), fetch, merge again (tie histories if needed), and retry
  git fetch origin --prune
  if git ls-remote --exit-code --heads origin "${BRANCH}" >/dev/null 2>&1; then
    git merge --allow-unrelated-histories -s ours --no-edit "origin/${BRANCH}" || true
  fi
  git add -A
  if ! git diff --cached --quiet; then
    git commit -m "Resolve non-ff: keep local over origin/${BRANCH}"
  fi

  set +e
  git push origin "${BRANCH}"
  PUSH_CODE=$?
  set -e

  # Final fallback: overwrite remote with local (still safe-ish via --force-with-lease)
  if [ $PUSH_CODE -ne 0 ]; then
    git push --force-with-lease origin "${BRANCH}"
  fi
fi
echo "== finished"
