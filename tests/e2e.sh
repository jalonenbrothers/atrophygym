#!/bin/bash
# AtrophyGym e2e vs real Apache (php:8.3-apache) + MySQL 8. E1–E7 per UX plan.
set -u
B="http://127.0.0.1:18091"
J=/tmp/agym.cookies; rm -f $J
pass=0; fail=0
t() { if [ "$2" = "$3" ]; then pass=$((pass+1)); else fail=$((fail+1)); echo "FAIL: $1 (expected [$3] got [$2])"; fi; }
has() { if grep -q "$3" <<<"$2"; then pass=$((pass+1)); else fail=$((fail+1)); echo "FAIL: $1 (missing [$3])"; fi; }
nothas() { if grep -q "$3" <<<"$2"; then fail=$((fail+1)); echo "FAIL: $1 (unexpected [$3])"; else pass=$((pass+1)); fi; }

# --- E1 dashboard: empty state
R=$(curl -s -c $J $B/index.php)
t "E1 status" "$(curl -s -o /dev/null -w %{http_code} -c $J $B/index.php)" "200"
has "E1 empty headline" "$R" "What have you delegated to AI?"
has "E1 add-first button" "$R" "Add your first skill"

# --- E4 add skill: validation error first (empty name)
R=$(curl -s -b $J -c $J -d "action=save&name=&delegate_desc=&cadence_days=7" $B/skill-form.php)
has "E4 empty name error" "$R" "Give the skill a name."
R=$(curl -s -b $J -c $J -d "action=save&name=X&delegate_desc=&cadence_days=91" $B/skill-form.php)
has "E4 cadence error" "$R" "Cadence must be between 1 and 90 days."
# valid create → 303
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code} %{redirect_url}" -d "action=save&name=Writing first drafts&delegate_desc=I let AI draft every email. I only edit.&cadence_days=7" $B/skill-form.php)
has "E4 303 to skill.php" "$C" "303"
has "E4 redirect target" "$C" "skill.php?id=1"

# --- E2 skill detail (fresh, 0 reps)
R=$(curl -s -b $J -c $J "$B/skill.php?id=1")
t "E2 status" "$(curl -s -o /dev/null -w %{http_code} "$B/skill.php?id=1")" "200"
has "E2 name" "$R" "Writing first drafts"
has "E2 desc" "$R" "I let AI draft every email. I only edit."
has "E2 due badge (0 reps → Due)" "$R" "Due"
has "E2 curve empty state" "$R" "No reps yet. Log your first rep above."
has "E2 history empty state" "$R" "No reps yet. Your first rep starts the streak."
has "E2 log form present" "$R" "Log a no-AI rep"
has "E2 rating label" "$R" "1 — Fumbling"
has "E2 rating label 5" "$R" "5 — Fluent"

# --- E2 unknown id → 404
t "E2 unknown id 404" "$(curl -s -o /dev/null -w %{http_code} "$B/skill.php?id=999")" "404"
has "E2 404 body" "$(curl -s "$B/skill.php?id=999")" "Skill not found."
t "E2 bad id 404" "$(curl -s -o /dev/null -w %{http_code} "$B/skill.php?id=abc")" "404"

# --- E3 log rep: missing rating → inline error (200)
R=$(curl -s -b $J -c $J -d "action=log&rating=&note=" "$B/skill.php?id=1")
has "E3 no-rating error" "$R" "Pick a confidence rating first."
# valid rep → 303 + flash
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code}" -d "action=log&rating=3&note=Starting slow." "$B/skill.php?id=1")
t "E3 303" "$C" "303"
R=$(curl -s -b $J -c $J "$B/skill.php?id=1")
has "E3 flash streak 1" "$R" "Rep logged. Streak 1."
has "E3 curve n=1 state" "$R" "One rep — the curve starts with the second."
has "E3 history row" "$R" "3/5"
has "E3 note row" "$R" "Starting slow."

# second rep → streak 2, SVG curve
curl -s -b $J -c $J -o /dev/null -d "action=log&rating=4&note=" "$B/skill.php?id=1"
R=$(curl -s -b $J -c $J "$B/skill.php?id=1")
has "E3 flash streak 2" "$R" "Rep logged. Streak 2."
has "E4b curve path" "$R" "<path"
has "E4b curve circles" "$R" "<circle"
nothas "E2 no n=1 text at n=2" "$R" "the curve starts with the second"

# --- E5 edit
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code}" -d "action=save&name=Writing drafts&delegate_desc=AI drafts everything.&cadence_days=14" "$B/skill-form.php?id=1")
t "E5 303" "$C" "303"
R=$(curl -s -b $J -c $J "$B/skill.php?id=1")
has "E5 edited name" "$R" "Writing drafts"
has "E5 edited cadence" "$R" "every 14 days"
has "E5 saved flash" "$R" "Skill saved."

# --- second skill so dashboard has cards + global streak = MIN
curl -s -b $J -c $J -o /dev/null -d "action=save&name=Mental math&delegate_desc=&cadence_days=7" $B/skill-form.php
curl -s -b $J -c $J -o /dev/null -d "action=log&rating=2&note=" "$B/skill.php?id=2"

# --- E1 dashboard with cards
R=$(curl -s -b $J -c $J $B/index.php)
has "E1 skill card 1" "$R" "Writing drafts"
has "E1 skill card 2" "$R" "Mental math"
has "E1 streak strip" "$R" "Current streak"
has "E1 global min streak (1 of 2)" "$R" "1 days"
has "E1 cadence chip" "$R" "every 14 days"
has "E1 last rep" "$R" "last rep"
has "E1 data-skill-id" "$R" "data-skill-id"
# ordering: seed a second skill with no reps (Due) and check Due card ordering vs scheduled — both Due here; fine.

# --- E6 archive two-step (no JS)
# step 1: confirm=0 → re-render with "Really archive? Tap again"
R=$(curl -s -b $J -c $J -d "action=archive&confirm=0&name=Writing drafts&delegate_desc=x&cadence_days=14" "$B/skill-form.php?id=1")
has "E6 step1 confirm text" "$R" "Really archive? Tap again"
# step 2: confirm=1 → 303 to index.php
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code} %{redirect_url}" -d "action=archive&confirm=1&name=Writing drafts&delegate_desc=x&cadence_days=14" "$B/skill-form.php?id=1")
has "E6 step2 303" "$C" "303"
has "E6 redirect to index" "$C" "index.php"
R=$(curl -s -b $J -c $J $B/index.php)
has "E6 archived flash" "$R" "Archived. Its reps are kept."
nothas "E6 hidden from dashboard" "$R" "Writing drafts"
has "E6 archived link count" "$R" "Archived skills (1)"
has "E6 hidden card 2 still there" "$R" "Mental math"

# archived view: read-only, unarchive button
R=$(curl -s -b $J -c $J "$B/skill.php?id=1")
has "archived page msg" "$R" "This skill is archived."
nothas "no log form when archived" "$R" "Log a no-AI rep"
has "unarchive button" "$R" "Unarchive"

# --- E7 unarchive
C=$(curl -s -b $J -c $J -o /dev/null -w "%{http_code}" -d "action=unarchive" "$B/skill.php?id=1")
t "E7 303" "$C" "303"
R=$(curl -s -b $J -c $J "$B/skill.php?id=1")
has "E7 restored flash" "$R" "Skill restored."
has "E7 log form back" "$R" "Log a no-AI rep"
R=$(curl -s -b $J -c $J $B/index.php)
has "E7 back on dashboard" "$R" "Writing drafts"

# --- archived section (?archived=1) + 404s on internals (real Apache .htaccess)
R=$(curl -s -b $J -c $J "$B/index.php?archived=1")
has "E1 archived section" "$R" "Archived"
for p in lib/db.php src/Streak.php tests/run.php migrations/001_create_tables.sql lib/config.example.php; do
  t "404 on /$p" "$(curl -s -o /dev/null -w %{http_code} "$B/$p")" "404"
done

# --- XSS escaping check (name with HTML)
curl -s -b $J -c $J -o /dev/null -d "action=save&name=<script>alert(1)</script>&cadence_days=7" $B/skill-form.php
R=$(curl -s -b $J -c $J "$B/skill.php?id=3")
nothas "XSS name escaped" "$R" "<script>alert(1)"
has "XSS escaped entity" "$R" "&lt;script&gt;"
curl -s -b $J -c $J -o /dev/null -d "action=log&rating=5&note=<img src=x onerror=alert(2)>" "$B/skill.php?id=3"
R=$(curl -s -b $J -c $J "$B/skill.php?id=3")
nothas "XSS note escaped" "$R" "onerror=alert"

# cleanup XSS skill (archive)
curl -s -b $J -c $J -o /dev/null -d "action=archive&confirm=1&name=x&cadence_days=7" "$B/skill-form.php?id=3"

# --- DB sanity: rows and CHECK constraint
D=$(docker exec agym-mysql mysql -uagym -pagym atrophygym -N -e "SELECT COUNT(*) FROM reps WHERE skill_id=1;" 2>/dev/null)
t "rep rows for skill 1" "$D" "2"
D=$(docker exec agym-mysql mysql -uagym -pagym atrophygym -N -e "INSERT INTO reps (skill_id, rating, note, logged_at) VALUES (1, 9, '', '2026-11-09');" 2>&1)
has "CHECK rating 1..5 enforced" "$D" "Check constraint"

echo; echo "E2E RESULT: $pass passed, $fail failed"
exit $((fail > 0))
