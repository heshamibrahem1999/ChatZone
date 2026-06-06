  <div class="group-messages initial-bottom-pending" id="groupMessages" data-group-id="<?= $groupId ?>">
    <?php foreach ($timeline as $item): ?>
      <?php if ($item['kind'] === 'poll'): $poll = $item['data']; ?>
        <div id="poll-<?= (int)$poll['id'] ?>" class="group-msg poll-msg <?= ((int)$poll['created_by'] === $userId) ? 'mine' : '' ?>">
          <div class="group-bubble poll-bubble">
            <?php if ((int)$poll['created_by'] !== $userId): ?>
              <div class="group-name"><?= e(trim(($poll['first_name'] ?? '') . ' ' . ($poll['last_name'] ?? ''))) ?></div>
            <?php endif; ?>
            <div class="poll-card">
              <div class="poll-head">
                <div>
                  <div class="poll-question">📊 <?= e($poll['question']) ?> <?= ((int)$poll['is_closed'] === 1) ? '🔒' : '' ?></div>
                  <div class="poll-meta"><?= (int)$poll['total_votes'] ?> vote(s)</div>
                </div>
                <?php if (($group['role'] ?? '') === 'admin'): ?>
                  <button type="button" class="poll-toggle-btn" data-poll-id="<?= (int)$poll['id'] ?>"><?= ((int)$poll['is_closed'] === 1) ? 'Reopen' : 'Close' ?></button>
                <?php endif; ?>
              </div>
              <?php foreach (($poll['options'] ?? []) as $opt):
                $votes = (int)$opt['votes'];
                $total = max(1, (int)$poll['total_votes']);
                $pct = round(($votes / $total) * 100);
                $isVoted = ((int)$poll['my_vote'] === (int)$opt['id']);
              ?>
                <button type="button" class="poll-option <?= $isVoted ? 'voted' : '' ?>" data-poll-id="<?= (int)$poll['id'] ?>" data-option-id="<?= (int)$opt['id'] ?>" <?= ((int)$poll['is_closed'] === 1) ? 'disabled' : '' ?>>
                  <?= e($opt['option_text']) ?> <?= $isVoted ? '✅' : '' ?>
                  <span style="float:right"><?= $votes ?> · <?= $pct ?>%</span>
                  <div class="poll-bar"><div class="poll-fill" style="width:<?= $pct ?>%"></div></div>
                </button>
              <?php endforeach; ?>
            </div>
            <div class="group-time"><?= e($poll['created_at'] ?? '') ?></div>
          </div>
        </div>
        <?php continue; endif; ?>
      <?php $m = $item['data']; ?>
      <div id="group-message-<?= (int)$m['id'] ?>" class="group-msg <?= ((int)$m['sender_id'] === $userId) ? 'mine' : '' ?> <?= ((int)($m['is_pinned'] ?? 0) === 1) ? 'pinned' : '' ?> <?= ((int)($m['mentioned_me'] ?? 0) === 1) ? 'mentioned-me' : '' ?>" data-message-id="<?= (int)$m['id'] ?>">
        <div class="group-bubble">
          <?php if ((int)$m['sender_id'] !== $userId): ?><div class="group-name"><?= e(trim($m['first_name'].' '.$m['last_name'])) ?></div><?php endif; ?>
          <?php if ((int)($m['is_pinned'] ?? 0) === 1): ?><div class="group-pinned">📌 Pinned</div><?php endif; ?>
          <?php if ((int)($m['is_starred'] ?? 0) === 1): ?><div class="group-starred">⭐ Starred</div><?php endif; ?>
          <?php if ((int)($m['is_forwarded'] ?? 0) === 1): ?><div class="group-forwarded">➡ Forwarded</div><?php endif; ?>
          <?php if ((int)($m['mentioned_me'] ?? 0) === 1): ?><div class="mention-badge">@ Mentioned you</div><?php endif; ?>
          <?php $type = $m['message_type'] ?? 'text'; $filePath = $m['file_path'] ?? ''; ?>
          <?php if ((int)($m['is_deleted'] ?? 0) === 1): ?>
            <div style="font-style:italic;opacity:.65;">This message was deleted</div>
          <?php else: ?>
            <?php if (!empty($m['reply_to_id'])): ?>
              <div class="group-reply-box">
                <strong>Reply to <?= e(trim(($m['reply_first_name'] ?? '') . ' ' . ($m['reply_last_name'] ?? ''))) ?></strong><br>
                <?= e(mb_strimwidth(($m['reply_body'] ?: '[' . ($m['reply_type'] ?? 'media') . ']'), 0, 70, '...')) ?>
              </div>
            <?php endif; ?>
            <?php if ($type === 'image' && $filePath): ?>
              <a href="<?= e($filePath) ?>" target="_blank"><img class="group-img" src="<?= e($filePath) ?>" alt="image"></a>
              <?php if (!empty($m['body'])): ?><div><?= render_group_message_body_with_mentions((string)$m['body']) ?></div><?php endif; ?>
            <?php elseif ($type === 'voice' && $filePath): ?>
              <audio class="group-audio" controls preload="metadata" src="<?= e($filePath) ?>"></audio>
              <?php if (!empty($m['body'])): ?><div><?= render_group_message_body_with_mentions((string)$m['body']) ?></div><?php endif; ?>
            <?php elseif ($type === 'file' && $filePath): ?>
              <a class="group-file" href="<?= e($filePath) ?>" target="_blank">📎 Download file</a>
              <?php if (!empty($m['body'])): ?><div><?= render_group_message_body_with_mentions((string)$m['body']) ?></div><?php endif; ?>
            <?php else: ?>
              <div><?= render_group_message_body_with_mentions((string)$m['body']) ?></div>
            <?php endif; ?>
            <?php if (!empty($m['edited_at'])): ?><span style="font-size:11px;opacity:.6;">edited</span><?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($m['reactions'])): ?><div class="group-reactions"><?= e($m['reactions']) ?></div><?php endif; ?>
          <?php if ((int)($m['is_deleted'] ?? 0) !== 1): ?>
          <button type="button" class="mobile-group-menu-btn" aria-label="Message actions">⋯</button>
          <div class="group-actions">
            <button type="button" class="group-action-btn group-reply-btn" data-message-id="<?= (int)$m['id'] ?>" data-preview="<?= e(mb_strimwidth($m['body'] ?: '[media]', 0, 60, '...')) ?>">↩</button>
            <?php foreach (['👍','❤️','😂','😮','😢','🙏','🔥'] as $emoji): ?>
              <button type="button" class="group-action-btn group-react-btn" data-message-id="<?= (int)$m['id'] ?>" data-emoji="<?= e($emoji) ?>"><?= e($emoji) ?></button>
            <?php endforeach; ?>
            <?php if ($group['role'] === 'admin' || (int)$m['sender_id'] === $userId): ?>
              <button type="button" class="group-action-btn group-pin-btn" data-message-id="<?= (int)$m['id'] ?>"><?= ((int)($m['is_pinned'] ?? 0) === 1) ? 'Unpin' : 'Pin' ?></button>
            <?php endif; ?>
            <button type="button" class="group-action-btn group-star-btn" data-message-id="<?= (int)$m['id'] ?>">⭐</button>
            <button type="button" class="group-action-btn group-forward-btn" data-source-type="group" data-message-id="<?= (int)$m['id'] ?>">➡</button>
            <?php if ((int)$m['sender_id'] === $userId): ?>
              <?php if (($m['message_type'] ?? 'text') === 'text'): ?><button type="button" class="group-action-btn group-edit-btn" data-message-id="<?= (int)$m['id'] ?>" data-body="<?= e($m['body'] ?? '') ?>">Edit</button><?php endif; ?>
              <button type="button" class="group-action-btn group-delete-btn" data-message-id="<?= (int)$m['id'] ?>">Del</button>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="group-time"><?= e($m['created_at']) ?></div>
          <?php if ((int)$m['sender_id'] === $userId): ?>
            <?php $rc = (int)($m['read_count'] ?? 0); ?>
            <div class="group-seen-count <?= $rc > 0 ? 'seen' : '' ?>">Seen by <?= $rc ?> member<?= $rc === 1 ? '' : 's' ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
