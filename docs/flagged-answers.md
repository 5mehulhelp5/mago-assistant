# Flagged answers

An administrator who gets a wrong, odd or unhelpful answer can flag it from the chat panel. The flag
keeps the turn, and *Mago Assistant → Flagged Answers* is where those turns are read back and
exported as debug material for an issue.

## What a flag is

A flag is a copy, not a pointer. At the moment it is made, the turn is serialised into the flag row:

| In the snapshot | Where it comes from |
| --- | --- |
| The flagged answer, with its tool calls | `mago_message` |
| The six messages before it | `mago_message` |
| Provider, model, tokens and skills used, over every provider call of the turn | `mago_usage_log` |
| The request and response payloads of each call | `mago_usage_log`, **only when debug logging was on** |
| Module version, Magento version, PHP version | runtime |

### Why a copy

Two things would otherwise empty a flag exactly when someone opens it:

- `UsageLogger` writes `request_payload` and `response_payload` only while
  *Stores → Configuration → Mago Assistant → Debug* is on. On a store where it is off, there is
  nothing to point at.
- `UsageLogCleaner` nulls those columns again after `mago/debug/payload_retention_days` (30 by
  default), and deleting a conversation cascades its messages away.

So `mago_flag.snapshot` holds its own copy, and `message_id` / `conversation_id` are foreign keys
with `ON DELETE SET NULL`: delete the conversation and the flag still reads. The integration test
`FlagRepositoryTest::itKeepsTheSnapshotWhenTheConversationIsDeleted` pins that.

A flag is never refreshed. It is what the answer looked like when someone thought it was wrong.

### Which usage rows belong to the turn

`mago_usage_log` rows carry a conversation and a timestamp, not a message. A turn runs from the
message that started it (the question, or the answer that was waiting for a confirmation) to the
flagged answer, and every usage row in that window is copied. A tool-using turn logs one row per
provider call, so tokens are summed and the skills of all calls are merged. A slash command makes no
provider call and gets no usage at all, rather than the previous turn's.

### Privacy

Message text is stored tokenised (see privacy mode) and the snapshot keeps it that way, so the JSON
bundle carries tokens, not the values behind them. The Flagged Answers screen rehydrates them through
the conversation's vault while the conversation exists; after that they read as `[earlier record]`.

### Retention

A flag outlives deleting its conversation by hand, but not the history retention window
(`mago/privacy/history_retention_days`). The window is counted from when the flag was made, so
flagging an answer in an old conversation still keeps it for the full window. With retention off,
flags are kept until someone deletes them.

### Size

With debug logging on, every provider call of a turn brings its own payloads, and a long tool-heavy
turn can outgrow one database row. `SnapshotSizeLimit` then leaves payloads out, oldest call first,
so the call that produced the answer is kept longest, and the flag says it was trimmed.

## In the panel

The flag button sits under an assistant answer and appears on hover; once the answer is flagged it
stays visible and takes the accent colour. Clicking it again removes the flag, but only for the
admin who flagged it and only while it is still open. After that, removing it is a delete under
Flagged Answers.

The message id it needs is already there: the `done` SSE event carries `message_id` for every
answer, and `Chat\Load` marks the messages of a reloaded conversation that already carry a flag.
`Chat\Flag` checks ownership through `getMessageForUser()`, so a flag cannot become a way to read
someone else's conversation.

## In the admin

*Mago Assistant → Flagged Answers* lists them with status, a preview of the answer, the note, who
flagged it and which model answered. Opening one shows the answer, the messages leading to it, and
the payloads when they were captured — and says so plainly when they were not.

**Download JSON** gives the whole snapshot as a file to attach to an issue. It is sent from memory,
so no copy is left in `var/`.

> The bundle is the conversation as it was stored, so it can contain store and customer data. The
> screen says so above the button; read it before attaching it to a public issue.

## ACL

| Resource | Allows |
| --- | --- |
| `MagoAssistant_Mago::flags` | See the screen, open a flag, resolve or reopen it |
| `MagoAssistant_Mago::flags_export` | Download the JSON bundle |
| `MagoAssistant_Mago::flags_delete` | Delete flags |

A flag is a copy of another admin's conversation, so every Flagged Answers action also needs
`MagoAssistant_Mago::config`, the resource the Conversations screen is behind.

Flagging itself needs `MagoAssistant_Mago::assistant_read` — anyone who can use the panel can flag
what it answers.
