#!/bin/bash
echo "Let me see"
conflicts=`git diff -S '<<<<<< HEAD'`
if [ -n "$conflicts" ]; then
  echo "!!COMMIT REJECTED!! You have merge conflicts in the following file(s):"
  echo "$conflicts"
  exit 1
fi