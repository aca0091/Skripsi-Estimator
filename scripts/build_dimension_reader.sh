#!/bin/sh
# Build the local macOS Vision reader. Requires Apple's Command Line Tools.
set -eu
cd "$(dirname "$0")/.."
mkdir -p scripts/bin
swiftc -module-cache-path "${TMPDIR:-/tmp}/estimator-swift-cache" scripts/read_dimensions.swift -o scripts/bin/read_dimensions
