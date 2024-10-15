#!/bin/bash

# Wrapper script for the Amazon Rank Updater PHP program

# Function for help text
show_help() {
    cat << EOF
Usage: ${0##*/} [OPTION]... [FILE]...
Wrapper for the Amazon Rank Updater PHP program with additional functionality.

Options:
    -h, --help      Display this help and exit
    -v, --verbose   Increase verbosity
    -o, --output    Specify output file
    --debug         Run in debug mode (enabled by default)

Examples:
    ${0##*/} input.txt
    ${0##*/} -v -o output.txt input1.txt input2.txt

For more information, see the full documentation at:
https://example.com/amazon-rank-updater-docs
EOF
}

# Default values
VERBOSE=0
OUTPUT_FILE=""
DEBUG=1  # Debug mode enabled by default
PHP_SCRIPT="amazon_rank_updater.php"  # Name of the PHP script

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
        -o|--output)
            OUTPUT_FILE="$2"
            shift 2
            ;;
        --debug)
            DEBUG=1
            shift
            ;;
        *)
            break
            ;;
    esac
done

# Check if input files were specified
if [ $# -eq 0 ]; then
    echo "Error: No input files specified." >&2
    show_help
    exit 1
fi

# Set environment variables based on options
[ $VERBOSE -eq 1 ] && export PHP_VERBOSE=1
[ $DEBUG -eq 1 ] && export PHP_DEBUG=1

# Set an environment variable to indicate that the wrapper is being used
export WRAPPER_SCRIPT=1

# Build the command for the PHP script
CMD="php $PHP_SCRIPT"
[ -n "$OUTPUT_FILE" ] && CMD="$CMD -o $OUTPUT_FILE"

# Execute the PHP script with the remaining arguments
if [ $VERBOSE -eq 1 ]; then
    echo "Executing: $CMD $@"
fi

exec $CMD "$@"
