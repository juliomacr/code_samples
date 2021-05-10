<?php

// This code sniped will allow us to replace the values on a form before sending the email
// so we can modify and update the email and provide additional details.

// This as part of a customer requirement to include a google maps link formed using the address fields

// We can use Google Maps to make a search with the values provided on the address field. The search action displays results for a search across the visible map region. When searching for a specific place, the resulting map puts a pin in the specified location and displays available place details. Forming the Search URL
// https://www.google.com/maps/search/?api=1¶meters
// Parameters:
// query (required): Defines the place(s) to highlight on the map. The query parameter is required for all search requests.
// Specify locations as either a place name, address, or comma-separated latitude/longitude coordinates. Strings should be URL-escaped, so an address such as "City Hall, New York, NY" should be converted to City+Hall%2C+New+York%2C+NY. 

// Following the example provide by the customer, the address link should look like this:
// 4928 stephens lane
// durham, nc, 27712
// united states

// https://www.google.com/maps/search/?api=1&query=4928+stephens+lane+durham+nc+27712 

// Make sure to update the form ID on line 30 and to update the correct field to replace
// this example is using 'your-name' tag instead of address

//This code needs to be added on the Theme Functions (functions.php)

add_action("wpcf7_before_send_mail", "wpcf7_do_something");

function wpcf7_do_something($WPCF7_ContactForm)
{
    if (26 == $WPCF7_ContactForm->id()) {

        //Get current form
        $wpcf7 = WPCF7_ContactForm::get_current();

        // get current SUBMISSION instance
        $submission = WPCF7_Submission::get_instance();

        // Ok go forward
        if ($submission) {

            // get submission data
            $data = $submission->get_posted_data();

            // nothing's here... do nothing...
            if (empty($data))
                return;

            // extract posted data for example to get name and change it
            $name = isset($data['your-name']) ? $data['your-name'] : "";

            // replace the spaces on the field
            $name = str_replace(" ","+",$name);
            // do some replacements in the cf7 email body
            $mail = $wpcf7->prop('mail');

            // Find/replace the "[your-name]" tag as defined in your CF7 email body
            // and add changes name
            $mail['body'] = str_replace('[your-name]', $name, $mail['body']);

            // Save the email body
            $wpcf7->set_properties(array(
                "mail" => $mail
            ));

            // return current cf7 instance
            return $wpcf7;
        }
    }
}
