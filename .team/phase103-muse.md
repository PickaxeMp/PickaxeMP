# Phase 10.3 inceleme istegi — `ItemStackRequest` (147/148)

Sen kıdemli... Görev: gerçek hataları bulmak.

We need answer in Turkish, madde madde, max 900 kelime.

Provide findings.

Let's analyze code.

1. Dupe/loss? move method: destinationStack->add($amount); then write destinationStack back. But destinationStack is reference from read? read returns $slots[$node[1]] ?? null which is reference to same object in $slots array. So destinationStack is same object. Then add mutates it, then write writes same reference. That's okay.

But sourceStack also reference. If amount < count, sourceStack->remove($amount); then write sourceStack back. That's okay.

Potential bug: move when destination null and amount > maxStackSize -> throws CANNOT_PLACE_ITEM. Good.

But when destination null, they write sourceStack->withCount($amount) to destination, and later modify sourceStack. Since withCount returns new object, fine.

Swap: writes both.

Touch only tracks inventory, not cursor? touch called for both source and destination, but touch only adds if inventory. So touched list correct.

Containers response: slotInfos uses requestedSlot: $index, slot: $index, amount: $slots[$index]?->count() ?? 0. Good.

Cursor response amount correct.

Potential dupe: sameNode check prevents same slot. Good.

But resolve checks containerName === self::CONTAINER_INVENTORY, slot range 0-35. INVENTORY_SIZE =36. Good.

Cursor slot must be 0.

Issue: netId ignored, but resolve doesn't check netId. Contract says netId = null always. OK.

RequestId validation: hasValidRequestId() presumably checks negative odd. If fails return error.

canInteract or actions empty -> error ACTION_REQUEST_NOT_ALLOWED.

Atomicity: they copy slots via copy() of each stack. cursor copy. Then operate on copies. On failure throw, return error without applying. Good.

But sameStack comparison for applying: they compare before vs after using sameStack which checks type, count, maxStackSize. If stack changed only metadata? Might miss. But okay.

Potential bug: move when destinationStack not null and canMergeWith true, they do $destinationStack->add($amount); then write. But destinationStack is reference to slots entry. So slots updated. Good.

But sourceStack removal: if amount === sourceStack->count() they write null. Else they remove and write back. However sourceStack->remove($amount) mutates object that is also in slots? Yes read returns reference. So fine.

Potential dupe: move with source = cursor, destination = inventory. read returns cursor reference. sourceStack is cursor object. After move, they write destination, then if amount < count they remove from sourceStack and write cursor = sourceStack (same object). Works.

Potential bug: touch does not mark inventory slot when source/destination is cursor? That's fine because cursor touched flag separate.

But containers() builds response for inventory only for touched indices. If a move involves cursor -> inventory, touched includes inventory slot. Good.

Potential bug: sameStack uses maxStackSize equality. If stack type same but maxStackSize changed? Unlikely.

Bug 2: amount limits. amount is uint8 from wire, but code checks amount <1 or > sourceStack->count(). No check >64 or > MAX_COUNT. Encoder forces 1-64. But if malicious packet bypass, amount could be 0-255. amount=0 would pass? amount <1 -> throws INVALID_REMOVED_AMOUNT. Good. amount > source count -> throws. So okay.

But move when destination null: they check amount > sourceStack->maxStackSize() -> throw CANNOT_PLACE_ITEM. But maxStackSize could be >128? MAX_COUNT=128. Might allow >128? Possibly.

Negative amount impossible due to uint8.

Same source/destination checked.

Bug 3: atomicity reference leak: $slots built with $stack?->copy(). Good deep copy? copy() presumably deep. cursor copy.

But in move, they call $this->read which returns reference to $slots entry. That's fine.

Potential bug: $destinationStack->add($amount) mutates object, but they didn't copy before? It's already copy.

Potential bug: sameStack for applying uses maxStackSize equality, but ItemStack::withCount returns new object with same maxStackSize. OK.

Bug 4: cursor map lifecycle. forget() unsets. If player disconnects, cursor remains in memory until forget called. Could leak. Also if canInteract false, request rejected before any mutation, cursor untouched. Good.

But cursor not persisted: on disconnect, items in cursor lost. Question 8.

Bug 5: server authority: they validate source/destination container, slot range, amount vs source count, mergeability, remaining space. Good. On failure they return error and do not apply. Since apply happens after try, good.

But they also check isSupported() before resolve. Good.

Potential bug: resolve throws INVALID_SOURCE_CONTAINER for dynamicId not null. Contract says netId null always. Good.

Bug 6: response correctness: requestId passed. result code from failure. On success, containers built. For inventory, they send slotInfos with requestedSlot = index, slot = index. Good. Amount = count or 0. Good.

But containers order: inventory first then cursor. Might be fine.

Potential bug: containers() for inventory includes only touched slots, but if cursor touched and inventory touched, they return two containers. Good.

Bug 7: protocol compliance: requestId negative odd check done. netId null ignored. type>=9 variant rule not checked? isSupported() presumably checks type. Might be okay.

Potential bug: they never check MAX_REQUESTS / MAX_RESPONSES. Not implemented.

Potential bug: they treat Swap amount field as unused, but action->amount may be non-zero. Not validated.

Potential bug: move for Take/Place: they don't differentiate Take vs Place semantics. Both use same move logic. Might be okay per contract.

Potential bug: sameNode check prevents same slot, but allows source inventory slot A -> destination inventory slot B, and later another action source B -> destination A in same request, causing net zero but intermediate state may allow overflow? Since all on copy, atomic, fine.

Potential dupe scenario: Swap with source cursor and destination inventory slot containing same stack reference? No.

Potential bug: touch only records inventory nodes. If source is inventory and destination is inventory, both touched. Good.

Potential bug: after successful request, they update inventory slots only for touched indices via setSlot. But if cursor touched, they update cursor map. However they never update inventory slots that were touched but unchanged? They compare sameStack before/after, good.

Potential bug: sameStack returns true if both null. Good.

Potential bug: in containers(), they use $slots[$index]?->count() ?? 0. If slot was null after move, amount 0. Good.

Potential bug: cursorTouched flag set if source or destination is cursor. But if action moves from cursor to cursor? sameNode would prevent. If source cursor, destination inventory, cursorTouched true. Good.

Potential bug: move when destinationStack not null and canMergeWith true but remainingSpace < amount -> throws INVALID_ADJUSTED_AMOUNT. Good.

Potential bug: they don't check that amount <= MAX_COUNT? Could be >128 but <=64 so fine.

Potential bug: ItemStack::add mutates count possibly exceeding maxStackSize? They checked remainingSpace < amount. So safe.

Potential bug: sourceStack->withCount($amount) creates new stack with count amount, but does it preserve NBT? Probably.

Potential bug: In move, when destination null, they don't check that sourceStack can be split? They check amount <= source count. Good.

Potential dupe: If source = inventory slot A, destination = cursor, and amount = source count, they write null to source, write stack to cursor. Good.

Potential bug: resolve returns ['inventory', slot] for container 12. But inventory size is 36, but CombinedHotbarAndInventory includes hotbar 0-8 and main 9-35. That's 36. Good.

Potential bug: they allow slot 0-35 inclusive. Good.

Potential bug: they don't validate that source container is allowed for Take vs Place. Contract says only container check.

Potential bug: netId ignored, but slotInfo contains netId int32 LE. They don't validate. Could be okay per contract.

Potential bug: requestId negative odd check: hasValidRequestId() maybe checks odd? Not shown.

Potential bug: they return error for empty actions with ACTION_REQUEST_NOT_ALLOWED. Contract says? Might be okay.

Potential bug: cursor map keyed by sessionId string. If player reconnects with same sessionId? Might reuse.

Potential bug: In handle, they copy inventory contents via foreach $inventory->contents() as $index => $stack. If contents returns sparse array, slots missing indices remain undefined. Later read returns null for missing. Might be okay.

Potential bug: touch uses $touched[$node[1]] = true for inventory. If same slot touched multiple times, fine.

Potential bug: containers() builds slotInfos with requestedSlot: $index, slot: $index. Protocol expects requestedSlot maybe original request slot? Might be okay.

Potential bug: they never clear cursor on successful move that empties cursor. They set cursor = null via write, then cursorTouched true, then they unset cursor map if cursor === null. Good.

Potential bug: If request fails after some actions mutated copy, they discard copy, no mutation. Good.

Potential bug: sameStack uses maxStackSize equality, but ItemStack copy may have different object identity. Fine.

Potential bug: In move, when destinationStack not null and canMergeWith, they do $destinationStack->add($amount); then write. But they didn't check that sourceStack count >= amount? Already checked.

Potential bug: If source and destination are same container but different slots, and source stack is same object as destination? No.

Potential bug: Swap with cursor: read returns cursor reference, write sets cursor. Good.

Potential bug: The code does not validate that amount for Swap is zero. Could be non-zero but ignored.

Potential bug: The code does not validate filterStrings. Not required.

Potential bug: The code does not enforce that Take action source must be inventory/cursor and destination cursor? Contract says server authoritative, so maybe okay.

Overall main issues:

- Cursor loss on disconnect (design).
- Swap amount ignored.
- Potential reference leak: $slots array contains copies, but read returns reference to same object, and add/remove mutates it. That's intended. But sameStack comparison uses maxStackSize equality which may be unnecessary.

- Potential bug: touch does not track inventory slot when node is cursor? Not needed.

- Potential bug: containers response for inventory includes slotInfos with amount, but protocol may require also type? Not shown.

- Potential bug: requestId validation returns ERROR (1) but contract says Error (1). Good.

- Potential bug: they throw INVALID_REQUEST_ACTION_TYPE when slot null. Might be wrong code.

Maybe dupe via same slot touched twice in one request: first move A->B, second move B->C. Works.

Potential dupe via move with source cursor and destination inventory, then later move same inventory slot back to cursor with amount > original? Since copy, atomic.

I think main real bug: move when destination null, they check amount > sourceStack->maxStackSize() -> throw CANNOT_PLACE_ITEM. But maxStackSize could be 64, amount 64 ok. But they don't check that sourceStack->maxStackSize() >= amount? Actually they check amount > maxStackSize. Good.

Potential bug: they don't check that sourceStack count after removal is not zero? They set null if equal.

Potential bug: sameStack comparison
