#!/bin/bash
# Ralph Wiggum Multi-PRD - Processes multiple PRDs sequentially
# Usage: ./ralph-multi.sh [--tool amp|claude] [--max-per-prd N] [--prds-dir DIR]
#
# Reads prd.json files from a directory (default: ./prds/) in order:
#   prd-001-*.json, prd-002-*.json, etc.
# When all stories in a PRD pass, archives it and moves to the next.

set -e

# Defaults
TOOL="claude"
MAX_PER_PRD=15
PRDS_DIR=""
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# Parse arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    --tool)
      TOOL="$2"
      shift 2
      ;;
    --tool=*)
      TOOL="${1#*=}"
      shift
      ;;
    --max-per-prd)
      MAX_PER_PRD="$2"
      shift 2
      ;;
    --max-per-prd=*)
      MAX_PER_PRD="${1#*=}"
      shift
      ;;
    --prds-dir)
      PRDS_DIR="$2"
      shift 2
      ;;
    --prds-dir=*)
      PRDS_DIR="${1#*=}"
      shift
      ;;
    *)
      if [[ "$1" =~ ^[0-9]+$ ]]; then
        MAX_PER_PRD="$1"
      fi
      shift
      ;;
  esac
done

# Validate tool
if [[ "$TOOL" != "amp" && "$TOOL" != "claude" ]]; then
  echo "Error: Invalid tool '$TOOL'. Must be 'amp' or 'claude'."
  exit 1
fi

# Set PRDs directory
if [ -z "$PRDS_DIR" ]; then
  PRDS_DIR="$SCRIPT_DIR/prds"
fi

PRD_FILE="$SCRIPT_DIR/prd.json"
PROGRESS_FILE="$SCRIPT_DIR/progress.txt"
ARCHIVE_DIR="$SCRIPT_DIR/archive"

# Check prds directory exists
if [ ! -d "$PRDS_DIR" ]; then
  echo "Error: PRDs directory not found: $PRDS_DIR"
  echo "Create it and add prd-001-*.json, prd-002-*.json, etc."
  exit 1
fi

# Find all PRD files sorted by name
PRD_FILES=($(ls "$PRDS_DIR"/prd-*.json 2>/dev/null | sort))

if [ ${#PRD_FILES[@]} -eq 0 ]; then
  echo "Error: No prd-*.json files found in $PRDS_DIR"
  exit 1
fi

echo "========================================"
echo "  Ralph Multi-PRD Mode"
echo "  Tool: $TOOL"
echo "  Max iterations per PRD: $MAX_PER_PRD"
echo "  PRDs found: ${#PRD_FILES[@]}"
echo "  Project root: $PROJECT_ROOT"
echo "========================================"

# Safety: backup CLAUDE.md before starting
cp "$SCRIPT_DIR/CLAUDE.md" "$SCRIPT_DIR/.CLAUDE.md.backup" 2>/dev/null || true

for prd_path in "${PRD_FILES[@]}"; do
  prd_name=$(basename "$prd_path" .json)

  # Check if this PRD is already fully completed
  all_pass=$(jq '[.userStories[].passes] | all' "$prd_path" 2>/dev/null || echo "false")
  if [ "$all_pass" == "true" ]; then
    echo ""
    echo "[SKIP] $prd_name — all stories already pass"
    continue
  fi

  # Count remaining stories
  total=$(jq '.userStories | length' "$prd_path")
  done_count=$(jq '[.userStories[] | select(.passes == true)] | length' "$prd_path")
  remaining=$((total - done_count))

  echo ""
  echo "========================================"
  echo "  Starting: $prd_name"
  echo "  Stories: $done_count/$total done, $remaining remaining"
  echo "========================================"

  # Copy this PRD as the active prd.json
  cp "$prd_path" "$PRD_FILE"

  # Get branch info
  branch=$(jq -r '.branchName // "unknown"' "$PRD_FILE")
  description=$(jq -r '.description // ""' "$PRD_FILE")

  # Initialize/reset progress file for this PRD
  if [ ! -f "$PROGRESS_FILE" ] || ! grep -q "$branch" "$PROGRESS_FILE" 2>/dev/null; then
    echo "# Ralph Progress Log — $prd_name" > "$PROGRESS_FILE"
    echo "PRD: $description" >> "$PROGRESS_FILE"
    echo "Branch: $branch" >> "$PROGRESS_FILE"
    echo "Started: $(date)" >> "$PROGRESS_FILE"
    echo "---" >> "$PROGRESS_FILE"
  fi

  # Run iterations for this PRD
  prd_complete=false
  for i in $(seq 1 $MAX_PER_PRD); do
    echo ""
    echo "---------------------------------------------------------------"
    echo "  [$prd_name] Iteration $i of $MAX_PER_PRD ($TOOL)"
    echo "---------------------------------------------------------------"

    # Safety: restore CLAUDE.md if it was deleted
    if [ ! -f "$SCRIPT_DIR/CLAUDE.md" ]; then
      echo "[SAFETY] CLAUDE.md was deleted — restoring from backup"
      cp "$SCRIPT_DIR/.CLAUDE.md.backup" "$SCRIPT_DIR/CLAUDE.md"
    fi

    # Run the agent from the project root
    cd "$PROJECT_ROOT"

    if [[ "$TOOL" == "amp" ]]; then
      OUTPUT=$(cat "$SCRIPT_DIR/prompt.md" | amp --dangerously-allow-all 2>&1 | tee /dev/stderr) || true
    else
      OUTPUT=$(claude --dangerously-skip-permissions --print < "$SCRIPT_DIR/CLAUDE.md" 2>&1 | tee /dev/stderr) || true
    fi

    cd "$SCRIPT_DIR"

    # Safety: restore CLAUDE.md again after agent run
    if [ ! -f "$SCRIPT_DIR/CLAUDE.md" ]; then
      echo "[SAFETY] CLAUDE.md was deleted by agent — restoring"
      cp "$SCRIPT_DIR/.CLAUDE.md.backup" "$SCRIPT_DIR/CLAUDE.md"
    fi

    # Check for completion signal
    if echo "$OUTPUT" | grep -q "<promise>COMPLETE</promise>"; then
      echo ""
      echo "[DONE] $prd_name completed at iteration $i!"
      prd_complete=true
      break
    fi

    echo "[$prd_name] Iteration $i complete. Continuing..."
    sleep 2
  done

  # Sync the active prd.json back to the source file
  cp "$PRD_FILE" "$prd_path"

  # Archive completed PRD
  if [ "$prd_complete" = true ]; then
    DATE=$(date +%Y-%m-%d)
    FOLDER_NAME=$(echo "$branch" | sed 's|^ralph/||')
    ARCHIVE_FOLDER="$ARCHIVE_DIR/$DATE-$FOLDER_NAME"

    echo "Archiving: $prd_name → $ARCHIVE_FOLDER"
    mkdir -p "$ARCHIVE_FOLDER"
    cp "$prd_path" "$ARCHIVE_FOLDER/"
    [ -f "$PROGRESS_FILE" ] && cp "$PROGRESS_FILE" "$ARCHIVE_FOLDER/"
  else
    echo ""
    echo "[WARN] $prd_name did NOT complete within $MAX_PER_PRD iterations."
    echo "       Remaining stories saved. Re-run to retry."
  fi
done

# Final summary
echo ""
echo "========================================"
echo "  Ralph Multi-PRD Summary"
echo "========================================"

all_done=true
for prd_path in "${PRD_FILES[@]}"; do
  prd_name=$(basename "$prd_path" .json)
  total=$(jq '.userStories | length' "$prd_path")
  done_count=$(jq '[.userStories[] | select(.passes == true)] | length' "$prd_path")

  if [ "$done_count" -eq "$total" ]; then
    status="COMPLETE"
  else
    status="$done_count/$total"
    all_done=false
  fi

  echo "  $prd_name: $status"
done

echo "========================================"

if [ "$all_done" = true ]; then
  echo "All PRDs completed successfully!"
  exit 0
else
  echo "Some PRDs have remaining work. Re-run to continue."
  exit 1
fi
