<?php
/*
 * Friendships. A row (requester, target) with accepted = 0 is a pending request.
 */

/** 'self', 'none', 'friends', 'sent' (I asked them) or 'received' (they asked me) */
function friend_status($me, $other)
{
    $me = (int)$me;
    $other = (int)$other;
    if ($me === $other) return 'self';
    $row = db_row('SELECT requester_id, accepted FROM friends WHERE (requester_id = ? AND target_id = ?) OR (requester_id = ? AND target_id = ?)', array($me, $other, $other, $me));
    if (!$row) return 'none';
    if ((int)$row['accepted'] === 1) return 'friends';
    return (int)$row['requester_id'] === $me ? 'sent' : 'received';
}

/** Returns the new status or an error message prefixed with '!'. */
function friend_request($me, $other)
{
    $status = friend_status($me, $other);
    if ($status === 'self') return '!You cannot be friends with yourself.';
    if ($status === 'friends' || $status === 'sent') return $status;
    if ($status === 'received') return friend_accept($me, $other);
    if ((int)db_value('SELECT COUNT(*) FROM friends WHERE requester_id = ? AND accepted = 0', array((int)$me)) >= 50) {
        return '!You have too many friend requests waiting. Wait for some answers first.';
    }
    db_query('INSERT INTO friends (requester_id, target_id, accepted, created) VALUES (?, ?, 0, ?)', array((int)$me, (int)$other, time()));
    return 'sent';
}

function friend_accept($me, $other)
{
    db_query('UPDATE friends SET accepted = 1 WHERE requester_id = ? AND target_id = ?', array((int)$other, (int)$me));
    return friend_status($me, $other);
}

/** Declines a request, cancels my request, or ends a friendship. */
function friend_remove($me, $other)
{
    db_query('DELETE FROM friends WHERE (requester_id = ? AND target_id = ?) OR (requester_id = ? AND target_id = ?)', array((int)$me, (int)$other, (int)$other, (int)$me));
    return 'none';
}

function friend_count($userId)
{
    return (int)db_value('SELECT COUNT(*) FROM friends WHERE accepted = 1 AND (requester_id = ? OR target_id = ?)', array((int)$userId, (int)$userId));
}

function friends_of($userId, $limit = 6, $offset = 0)
{
    return db_all('SELECT u.* FROM friends f JOIN users u ON u.id = CASE WHEN f.requester_id = ? THEN f.target_id ELSE f.requester_id END
                   WHERE f.accepted = 1 AND (f.requester_id = ? OR f.target_id = ?)
                   ORDER BY u.last_seen DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset, array((int)$userId, (int)$userId, (int)$userId));
}

function friend_requests_for($userId)
{
    return db_all('SELECT u.*, f.created AS requested FROM friends f JOIN users u ON u.id = f.requester_id WHERE f.target_id = ? AND f.accepted = 0 ORDER BY f.created DESC', array((int)$userId));
}

function friend_request_count($userId)
{
    return (int)db_value('SELECT COUNT(*) FROM friends WHERE target_id = ? AND accepted = 0', array((int)$userId));
}
