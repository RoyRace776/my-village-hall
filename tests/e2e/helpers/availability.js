async function findNextSlotFromAvailabilityService(page, roomId, lengthMinutes = 60) {
  const result = await page.evaluate(async ({ selectedRoomId, requestedLength }) => {
    const payload = new FormData();
    payload.append('action', 'myvh_portal_next_booking_slot');
    payload.append('nonce', window.myvhPortal.nonce);
    payload.append('room_id', String(selectedRoomId));
    payload.append('length_minutes', String(requestedLength));

    const response = await fetch(window.myvhPortal.ajax_url, {
      method: 'POST',
      body: payload,
      credentials: 'same-origin',
    });

    return response.json();
  }, { selectedRoomId: roomId, requestedLength: lengthMinutes });

  if (!result || !result.success || !result.data) {
    throw new Error(result?.message || 'Failed to fetch next available booking slot.');
  }

  return result.data;
}

module.exports = {
  findNextSlotFromAvailabilityService,
};
