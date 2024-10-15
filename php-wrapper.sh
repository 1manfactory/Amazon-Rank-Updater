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
    ${0##*/}
    ${0##*/} -v -l
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
            shift
            ;;
        -l|--live)
            LIVE_MODE=1
            shift
            ;;
        *)
            echo "Unknown option: $1" >&2
            show_help
            exit 1
            ;;
    esac
done

# Set environment variables based on options
[ $VERBOSE -eq 1 ] && export PHP_VERBOSE=1
[ $LIVE_MODE -eq 1 ] && export PHP_LIVE_MODE=1

# Set an environment variable to indicate that the wrapper is being used
export WRAPPER_SCRIPT=1

# Change to the script directory
cd "$SCRIPT_DIR" || exit 1

# Check if the PHP script exists
if [ ! -f "amazon_rank_updater.php" ]; then
    echo "Error: amazon_rank_updater.php not found in $SCRIPT_DIR" >&2
    exit 1
fi

# Execute the PHP script
php amazon_rank_updater.php
