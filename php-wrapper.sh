#!/usr/bin/env bash

# Get the absolute path of the script
SCRIPT_PATH=$(readlink -f "${BASH_SOURCE[0]}")
SCRIPT_DIR=$(dirname "$SCRIPT_PATH")

# Function for help text
show_help() {
    cat << EOF
Usage: ${0##*/} [OPTION]...
Wrapper for the Amazon Rank Updater PHP program.

Options:
    -h, --help      Display this help and exit
    -v, --verbose   Increase verbosity
    -l, --live      Run in live mode (perform actual API calls)

Examples:
    ${0##*/} -v
    ${0##*/} -l
EOF
}

# Default values
VERBOSE=0
LIVE_MODE=0

# Process command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -v|--verbose)
            VERBOSE=1
            ;;
        -l|--live)
            LIVE_MODE=1
            ;;
        *)
            echo "Unknown option: $1" >&2
            show_help
            exit 1
            ;;
    esac
    shift
done

# Set environment variables based on options
export PHP_VERBOSE=${VERBOSE:-0}
export PHP_LIVE_MODE=${LIVE_MODE:-0}

# Set an environment variable to indicate that the wrapper is being used
export WRAPPER_SCRIPT=1

# Change to the script directory
cd "$SCRIPT_DIR" || exit 1

# Check if the PHP script exists
if [ ! -f "run.php" ]; then
    echo "Error: run.php not found in $SCRIPT_DIR" >&2
    exit 1
fi

# Execute the PHP script
php run.php
