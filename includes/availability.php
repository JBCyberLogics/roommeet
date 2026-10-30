<?php

function getAlternativeSlots(
    $conn,
    $participant_ids,
    $meeting_date,
    $duration_minutes = 60
) {

    $working_start = 8 * 60;   // 08:00 AM
    $working_end = 17 * 60;    // 05:00 PM

    $suggestions = [];

    /*
     * Remove duplicate participant IDs.
     */
    $participant_ids = array_unique($participant_ids);


    /*
     * Check possible starting times in 30-minute intervals.
     */
    for (
        $start_minutes = $working_start;
        $start_minutes + $duration_minutes <= $working_end;
        $start_minutes += 30
    ) {

        $end_minutes = $start_minutes + $duration_minutes;

        $start_time = sprintf(
            "%02d:%02d:00",
            floor($start_minutes / 60),
            $start_minutes % 60
        );

        $end_time = sprintf(
            "%02d:%02d:00",
            floor($end_minutes / 60),
            $end_minutes % 60
        );

        $slot_available = true;


        /*
         * Check every participant.
         */
        foreach ($participant_ids as $participant_id) {

            $participant_id = (int) $participant_id;


            /*
             * Check meetings created by the person
             * OR meetings where the person is a participant.
             */
            $stmt = $conn->prepare(
                "SELECT m.id
                 FROM meetings m
                 LEFT JOIN meeting_participants mp
                 ON m.id = mp.meeting_id
                 WHERE m.meeting_date = ?
                 AND m.status = 'scheduled'
                 AND (m.created_by = ? OR mp.user_id = ?)
                 AND m.start_time < ?
                 AND m.end_time > ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                "siiss",
                $meeting_date,
                $participant_id,
                $participant_id,
                $end_time,
                $start_time
            );

            $stmt->execute();

            $result = $stmt->get_result();


            if ($result->num_rows > 0) {

                $slot_available = false;

                $stmt->close();

                break;
            }

            $stmt->close();
        }


        /*
         * Add available slot to suggestions.
         */
        if ($slot_available) {

            $suggestions[] = [
    "start" => date("g:i A", strtotime($start_time)),
    "end" => date("g:i A", strtotime($end_time))
];
        }


        /*
         * Stop after three suggestions.
         */
        if (count($suggestions) >= 3) {
            break;
        }
    }


    return $suggestions;
}

?>