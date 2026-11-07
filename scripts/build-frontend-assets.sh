#!/bin/sh
# Compatibility entry point for existing Unix callers; npm uses Node directly.
set -eu
exec node "$(dirname "$0")/build-frontend-assets.mjs"
