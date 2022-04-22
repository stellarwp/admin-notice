#!/usr/bin/env bash
#
# Pre-flight checks to run when preparing a release.

set -e

current_branch="$(git branch --show-current)"

# Set up colors.
color_cyan="\033[0;36m"
color_green="\033[0;32m"
color_red="\033[0;31m"
color_reset="\033[0;0m"
color_yellow="\033[0;33m"

fail() {
    printf "${color_red}%s${color_reset}\n- %s\n" "[FAIL]" "$1"
    printf "\n${color_red}Pre-flight checks have failed for branch %s${color_reset}\n" "$current_branch"
    exit 1
}

if [[ "$current_branch" != "release/"* ]]; then
    printf "${color_yellow}%s${color_reset}\n" "The current branch (${current_branch}) does not appear to be a release branch, some pre-check flights may fail!"
fi

# Ensure that AdminNotice::VERSION matches the current branch.
printf "${color_cyan}%s ${color_reset}" "Verifying that the release branch matches AdminNotice::VERSION"
current_version="$(php -r 'require_once "src/AdminNotice.php"; echo StellarWP\AdminNotice\AdminNotice::VERSION;')"

if [[ "$current_branch" != *"$current_version" ]]; then
    fail "Failed asserting that branch ${current_branch} and AdminNotice::VERSION (${current_version}) reference the same version"
fi

printf "\n\n${color_green}%s${color_reset}\n" "Pre-flight checks have completed successfully!"
