#!/bin/bash
# AtrophyGym e2e — E1..E7 vs real Apache (ag-web :18090) + MySQL 8 (ag-mysql).
# DB must be empty of skills before running (auto-increment ids are captured dynamically).
set -u
B="http://127.0.0.1:18090"
J=/tmp/agym-frontend.cookies; rm -f $J
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then pass=$((pass+1)); else fail=$((fail+1)); echo "FAIL: $1 (expected [$3] got [$2])"; fi; }
has() { if grep -q -- "$3" <<<"$2"; then pass=$((pass+1)); else fail=$((fail+1)); echo "FAIL: $1 (missing [$3])"; fi; }
nothas() { if grep -q -- "$3" <<<"$2"; then fail=$((fail+1)); echo "FAIL: $1 (unexpected [$3])"; else pass=$((pass+1)); fi; }
MY="docker exec ag-mysql mysql -uatrophygym -pdev atrophygym -N -e"

# --- E1 empty dashboard
R=$(curl -s -c $J $B/index.php)
t "E1 status" "$(curl -s -o /dev/null -w %{http_code} $B/index.php)" "200"
has "E1 empty headline" "$R" "What have you delegated to AI?"
has "E1 add-first" "$R" "Add your first skill"

# --- E4 add skill: validation
R=$(curl -s -b $J -c $J -d "name=&delegate_desc=&cadence_days=7" $B/skill-form.php)
has "E4 empty name error" "$R" "Give the skill a name."
R=$(curl -s -b $J -c $J -d "name=X&delegate_desc=&cadence_days=91" $B/skill-form.php)
has "E4 cadence error" "$R" "Cadence must be between 1 and 90 days."
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code} %{redirect_url}" -d "name=Writing first drafts&delegate_desc=I let AI draft every email and post &mdash; I only edit.&cadence_days=7" $B/skill-form.php)
has "E4 303" "$C" "303"
ID=$(sed -E 's/.*skill\.php\?id=([0-9]+).*/\1/' <<<"$C")
t "E4 new id is numeric+nonzero" "$(grep -qE '^[1-9][0-9]*$' <<<"$ID" && echo ok)" "ok"
echo "using skill id=$ID"
S="$B/skill.php?id=$ID"

# --- E2 detail fresh skill
R=$(curl -s -b $J -c $J "$S")
has "E2 name" "$R" "Writing first drafts"
has "E2 desc" "$R" "I let AI draft every email and post"
has "E2 streak line 0" "$R" "Current streak 0"
has "E2 due badge 0 reps" "$R" "Due"
has "E2 curve empty" "$R" "No reps yet. Log your first rep above."
has "E2 history empty" "$R" "No reps yet. Your first rep starts the streak."
has "E2 log form" "$R" "Log a no-AI rep"
has "E2 rating label" "$R" "1 &mdash; Fumbling"
has "E2 rating label 5" "$R" "5 &mdash; Fluent"

# 404s
t "E2 unknown id 404" "$(curl -s -o /dev/null -w %{http_code} "$B/skill.php?id=99999")" "404"
has "E2 404 body" "$(curl -s "$B/skill.php?id=99999")" "Skill not found."
t "E2 bad id 404" "$(curl -s -o /dev/null -w %{http_code} "$B/skill.php?id=abc")" "404"
t "E2 zero id 404" "$(curl -s -o /dev/null -w %{http_code} "$B/skill.php?id=0")" "404"

# --- E3 log rep
R=$(curl -s -b $J -c $J -d "rating=&note=" "$S&action=log")
has "E3 no-rating error" "$R" "Pick a confidence rating first."
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code}" -d "rating=3&note=Starting slow." "$S&action=log")
t "E3 303" "$C" "303"
R=$(curl -s -b $J -c $J "$S")
has "E3 flash streak 1" "$R" "Rep logged. Streak 1."
has "E3 curve n=1 note" "$R" "One rep"
has "E3 history row" "$R" "3/5"
has "E3 note row" "$R" "Starting slow."
# second rep same day → streak still 1 (same-day reps collapse by design), polyline curve
curl -s -b $J -c $J -o /dev/null -d "rating=4&note=" "$S&action=log"
R=$(curl -s -b $J -c $J "$S")
has "E3 same-day reps collapse to streak 1" "$R" "Current streak 1"
has "E3 curve polyline" "$R" "<polyline"
has "E3 curve circles" "$R" "<circle"
# array-input hostile rating → clean error, no warnings
R=$(curl -s -b $J -c $J -d "rating[]=5&note=" "$S&action=log")
has "E3 array rating → clean error" "$R" "Pick a confidence rating first."
nothas "E3 no PHP warning" "$R" "Warning"

# --- E5 edit
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code}" -d "name=Writing drafts&delegate_desc=AI drafts everything.&cadence_days=14" "$B/skill-form.php?id=$ID")
t "E5 303" "$C" "303"
R=$(curl -s -b $J -c $J "$S")
has "E5 flash" "$R" "Skill saved."
has "E5 edited name" "$R" "Writing drafts"
has "E5 edited cadence" "$R" "every 14 days"

# --- second skill + dashboard
ID2_C=$(curl -s -b $J -c $J -o /dev/null -w "%{redirect_url}" -d "name=Mental math&delegate_desc=&cadence_days=7" $B/skill-form.php)
ID2=$(sed -E 's/.*skill\.php\?id=([0-9]+).*/\1/' <<<"$ID2_C")
curl -s -b $J -c $J -o /dev/null -d "rating=2&note=" "$B/skill.php?id=$ID2&action=log"
R=$(curl -s -b $J -c $J $B/index.php)
has "E1 card 1" "$R" "Writing drafts"
has "E1 card 2" "$R" "Mental math"
has "E1 streak strip" "$R" "Current streak"
has "E1 global min = 1 (weakest muscle)" "$R" "<div class=\"num\">1 days</div>"
has "E1 cadence chip" "$R" "every 14 days"
has "E1 last rep" "$R" "last rep"
has "E1 data-skill-id" "$R" "data-skill-id"
has "E1 badges present" "$R" "badge badge-"

# --- E6 two-step archive
R=$(curl -s -b $J -c $J -d "confirm=0" "$B/skill-form.php?id=$ID&action=archive")
has "E6 step1 confirm text" "$R" "Really archive? Tap again"
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code} %{redirect_url}" -d "confirm=1" "$B/skill-form.php?id=$ID&action=archive")
has "E6 step2 303" "$C" "303"
has "E6 redirect index" "$C" "index.php"
R=$(curl -s -b $J -c $J $B/index.php)
has "E6 archived flash" "$R" "Archived. Its reps are kept."
nothas "E6 hidden from dashboard" "$R" "Writing drafts"
has "E6 archived link" "$R" "Archived skills (1)"
has "E6 other card remains" "$R" "Mental math"
R=$(curl -s -b $J -c $J "$S")
has "archived msg" "$R" "This skill is archived."
nothas "no log form archived" "$R" "Log a no-AI rep"
has "unarchive btn" "$R" "Unarchive"
# log on archived skill must not insert
curl -s -b $J -c $J -o /dev/null -d "rating=5&note=x" "$S&action=log"
D=$($MY "SELECT COUNT(*) FROM reps WHERE skill_id=$ID" 2>/dev/null)
t "no insert while archived" "$D" "2"

# --- E7 unarchive
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code}" -X POST "$S&action=unarchive")
t "E7 303" "$C" "303"
R=$(curl -s -b $J -c $J "$S")
has "E7 restored flash" "$R" "Skill restored."
has "E7 log form back" "$R" "Log a no-AI rep"
R=$(curl -s -b $J -c $J $B/index.php)
has "E7 back on dashboard" "$R" "Writing drafts"

# --- archived section
R=$(curl -s -b $J -c $J "$B/index.php?archived=1")
has "E1 archived section" "$R" "Archived skills"

# --- internals 404 (real Apache, .htaccess)
for p in lib/db.php lib/gym.php tests/run.php migrations/001_create_tables.sql lib/config.example.php; do
  t "404 on /$p" "$(curl -s -o /dev/null -w %{http_code} "$B/$p")" "404"
done

# --- XSS escaping
ID3_C=$(curl -s -b $J -c $J -o /dev/null -w "%{redirect_url}" -d "name=<script>alert(1)</script>&cadence_days=7" $B/skill-form.php)
ID3=$(sed -E 's/.*skill\.php\?id=([0-9]+).*/\1/' <<<"$ID3_C")
R=$(curl -s -b $J -c $J "$B/skill.php?id=$ID3")
nothas "XSS name escaped" "$R" "<script>alert(1)"
has "XSS entity" "$R" "&lt;script&gt;"
curl -s -b $J -c $J -o /dev/null -d "rating=5&note=<img src=x onerror=alert(2)>" "$B/skill.php?id=$ID3&action=log"
R=$(curl -s -b $J -c $J "$B/skill.php?id=$ID3")
nothas "XSS note escaped (no raw tag)" "$R" "<img src=x"
has "XSS note entity present" "$R" "&lt;img src=x onerror=alert(2)&gt;"

# --- DB CHECK constraint
D=$($MY "INSERT INTO reps (skill_id, rating, note, logged_at) VALUES ($ID, 9, '', '2026-11-09');" 2>&1)
has "CHECK rating enforced" "$D" "chk_rating"

echo; echo "E2E RESULT: $pass passed, $fail failed"
exit $((fail > 0))
