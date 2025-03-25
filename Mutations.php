/**
 * Combined GraphQL Mutations File
 *
 * This file combines the following mutation functionality:
 * - Jurisdiction mutations (creating and updating jurisdiction data)
 * - User mutations (updating user STD contact information)
 * - OOJ (Out of Jurisdiction) mutations (updating OOJ details)
 */

// Main GraphQL types registration hook
add_action('graphql_register_types', function() {

    /*********************************************************************************
     * JURISDICTION MUTATIONS
     * 
     * These mutations handle creating and updating jurisdiction data including:
     * - Address information
     * - Agency name
     * - Phone/fax numbers
     * - FIPS code
     *********************************************************************************/

    // Register fields for Create Jurisdiction mutation
    register_graphql_field('CreateJurisdictionInput', 'addressJurisdiction', [
        'type' => 'String',
        'description' => __('The jurisdiction address', 'your-textdomain') 
    ]);
    
    register_graphql_field('CreateJurisdictionInput', 'agencyName', [
        'type' => 'String', 
        'description' => __('The agency name', 'your-textdomain')
    ]);
    
    register_graphql_field('CreateJurisdictionInput', 'phoneJurisdiction', [
        'type' => 'String',
        'description' => __('The jurisdiction phone number', 'your-textdomain')
    ]);
    
    register_graphql_field('CreateJurisdictionInput', 'fipsCode', [
        'type' => 'String',
        'description' => __('The FIPS code', 'your-textdomain')
    ]);

    register_graphql_field('CreateJurisdictionInput', 'faxJurisdiction', [
        'type' => 'String',
        'description' => __('The jurisdiction fax number', 'your-textdomain')
    ]);

    // Register same fields for Update Jurisdiction mutation
    register_graphql_field('UpdateJurisdictionInput', 'addressJurisdiction', [
        'type' => 'String',
        'description' => __('The jurisdiction address', 'your-textdomain')
    ]);
    
    register_graphql_field('UpdateJurisdictionInput', 'agencyName', [
        'type' => 'String',
        'description' => __('The agency name', 'your-textdomain')
    ]);
    
    register_graphql_field('UpdateJurisdictionInput', 'phoneJurisdiction', [
        'type' => 'String',
        'description' => __('The jurisdiction phone number', 'your-textdomain')
    ]);
    
    register_graphql_field('UpdateJurisdictionInput', 'fipsCode', [
        'type' => 'String',
        'description' => __('The FIPS code', 'your-textdomain')
    ]);
    
    register_graphql_field('UpdateJurisdictionInput', 'faxJurisdiction', [
        'type' => 'String', 
        'description' => __('The jurisdiction fax number', 'your-textdomain')
    ]);

    /*********************************************************************************
     * USER MUTATIONS
     * 
     * These mutations handle updating user STD contact information including:
     * - HIV and STI roles (taxonomy terms)
     * - Contact information (phone, fax)
     * - Notes and jurisdiction assignment
     *********************************************************************************/
    
    register_graphql_mutation('updateUserStdContact', [
        'inputFields' => [
            'userId' => [
                'type' => 'ID',
                'description' => __('Global Relay ID of the User to update.', 'your-textdomain'),
            ],
            // The ACF fields:
            'hivRole'            => ['type' => ['list_of' => 'Int']], // Multi-select taxonomy => array of term IDs
            'stiRole'            => ['type' => ['list_of' => 'Int']], // Multi-select taxonomy => array of term IDs
            'userPhone'          => ['type' => 'String'],
            'confidentialFax'    => ['type' => 'String'],
            'userFax'            => ['type' => 'String'],
            'notesStiHiv'        => ['type' => 'String'],
            'userJurisdiction'   => ['type' => 'Int'], // Post ID for std_jurisdiction
        ],
        'outputFields' => [
            'user' => [
                'type' => 'User',
                'resolve' => function($payload, $args, $context, $info) {
                    if (!empty($payload['user_id'])) {
                        return get_user_by('ID', $payload['user_id']);
                    }
                    return null;
                }
            ]
        ],
        'mutateAndGetPayload' => function($input, $context, $info) {
            $user_id = \WPGraphQL\Utils\Utils::get_database_id_from_id($input['userId'], 'user');
            if (!$user_id) {
                throw new \GraphQL\Error\UserError('Invalid user ID.');
            }

            // 1. Update taxonomies (HIV Role, STI Role) if needed
            if (isset($input['hivRole'])) {
                // Store the array of term IDs directly in ACF
                update_field('hiv_role', $input['hivRole'], 'user_' . $user_id);
            }

            if (isset($input['stiRole'])) {
                update_field('sti_role', $input['stiRole'], 'user_' . $user_id);
            }

            // 2. Update other ACF fields
            if (isset($input['userPhone'])) {
                update_field('user_phone', $input['userPhone'], 'user_' . $user_id);
            }
            if (isset($input['confidentialFax'])) {
                update_field('confidential_fax', $input['confidentialFax'], 'user_' . $user_id);
            }
            if (isset($input['userFax'])) {
                update_field('user_fax', $input['userFax'], 'user_' . $user_id);
            }
            if (isset($input['notesStiHiv'])) {
                update_field('notes_sti_hiv', $input['notesStiHiv'], 'user_' . $user_id);
            }
            if (isset($input['userJurisdiction'])) {
                update_field('user_jurisdiction', $input['userJurisdiction'], 'user_' . $user_id);
            }

            // 3. Return the updated user
            return [
                'user_id' => $user_id,
            ];
        }
    ]);

    /*********************************************************************************
     * OOJ (OUT OF JURISDICTION) MUTATIONS
     * 
     * These mutations handle updating OOJ details including:
     * - OOJ Infection and Activity information
     * - Contact information and methods of transmitting
     * - Investigation details and acceptable PII flags
     *********************************************************************************/
    
    /**
     * 1) Define the input type for each row of the OOJ Details repeater
     */
    register_graphql_input_type('OOJDetailsInputRow', [
        'description' => __('Input for each row of the OOJ Details repeater.', 'your-textdomain'),
        'fields' => [
            'oojId'                                 => ['type' => 'Int'],
            'oojInfection'                          => ['type' => 'Int'],                  // Single term ID
            'oojActivity'                           => ['type' => ['list_of' => 'Int']],  // Multi-select term IDs
            'lastDateOfExposure'                    => ['type' => 'String'],
            'dispositionsReturned'                  => ['type' => 'String'],
            'acceptAndInvestigate'                  => ['type' => 'String'],
            'methodOfTransmitting'                  => ['type' => ['list_of' => 'Int']],
            'acceptableForPii'                      => ['type' => ['list_of' => 'Int']],  // Acceptable for PII
            'notes'                                 => ['type' => 'String'],
            'pointOfContacts'                       => ['type' => ['list_of' => 'Int']],  // Multiple contacts
        ],
    ]);

    /**
     * 2) Register the custom mutation that updates only the OOJ Details repeater
     */
    register_graphql_mutation('updateOOJDetails', [
        'inputFields' => [
            // The ID of the Jurisdiction (in Relay ID format)
            'id' => [
                'type' => 'ID',
                'description' => __('Global Relay ID of the Jurisdiction.', 'your-textdomain'),
            ],
            // Array of repeater rows
            'oojDetails' => [
                'type' => ['list_of' => 'OOJDetailsInputRow'],
                'description' => __('An array of rows to store in the OOJ Details repeater.', 'your-textdomain'),
            ],
        ],
        'outputFields' => [
            // Return the updated post
            'jurisdiction' => [
                'type' => 'Jurisdiction', // WPGraphQL type for your CPT
                'resolve' => function($payload, $args, $context, $info) {
                    if (isset($payload['post_id'])) {
                        return get_post($payload['post_id']);
                    }
                    return null;
                }
            ]
        ],
        'mutateAndGetPayload' => function($input, $context, $info) {
            // 1. Convert the Relay ID to a raw WordPress post ID
            $post_id = \WPGraphQL\Utils\Utils::get_database_id_from_id($input['id'], 'jurisdiction');
            if (empty($post_id) || 'std_jurisdiction' !== get_post_type($post_id)) {
                throw new \GraphQL\Error\UserError('Invalid jurisdiction ID.');
            }

            // 2. Build out the data array for the repeater if provided
            if (isset($input['oojDetails']) && is_array($input['oojDetails'])) {
                $repeater_values = [];

                foreach ($input['oojDetails'] as $detail) {
                    $row_data = [];

                    if (isset($detail['oojId'])) {
                        $row_data['ooj_id'] = $detail['oojId'];
                    }

                    if (isset($detail['oojInfection'])) {
                        $row_data['ooj_infection'] = $detail['oojInfection']; // single term ID
                    }

                    if (isset($detail['oojActivity']) && is_array($detail['oojActivity'])) {
                        $row_data['ooj_activity'] = $detail['oojActivity']; // array of term IDs
                    }

                    if (isset($detail['lastDateOfExposure'])) {
                        $row_data['last_date_of_exposure'] = $detail['lastDateOfExposure'];
                    }

                    if (isset($detail['dispositionsReturned'])) {
                        $row_data['dispositions_returned'] = $detail['dispositionsReturned'];
                    }

                    if (isset($detail['acceptAndInvestigate'])) {
                        $row_data['accept_and_investigate'] = $detail['acceptAndInvestigate'];
                    }

                    if (isset($detail['methodOfTransmitting']) && is_array($detail['methodOfTransmitting'])) {
                        $row_data['method_of_transmitting'] = $detail['methodOfTransmitting'];
                    }

                    if (isset($detail['acceptableForPii']) && is_array($detail['acceptableForPii'])) {
                        $row_data['acceptable_for_pii'] = $detail['acceptableForPii'];
                    }

                    if (isset($detail['notes'])) {
                        $row_data['notes'] = $detail['notes'];
                    }

                    if (isset($detail['pointOfContacts']) && is_array($detail['pointOfContacts'])) {
                        // For multi-select user field
                        $row_data['point_of_contacts'] = $detail['pointOfContacts'];
                    }

                    $repeater_values[] = $row_data;
                }

                // Update the entire repeater in one go
                update_field('ooj_details', $repeater_values, $post_id);
            }

            return [
                'post_id' => $post_id,
            ];
        }
    ]);
});

/*********************************************************************************
 * JURISDICTION MUTATION HANDLERS
 * 
 * These action hooks process the actual data updates for jurisdiction mutations
 *********************************************************************************/

// Handle the mutation data for jurisdiction updates
add_action('graphql_post_object_mutation_update_additional_data', function($post_id, $input, $post_type_object, $mutation_name) {
    error_log('=== Starting Jurisdiction Update ===');
    error_log('Post ID: ' . $post_id);
    error_log('Input data: ' . print_r($input, true));

    // Handle Address Jurisdiction
    if (isset($input['addressJurisdiction'])) {
        $result = update_field('address_jurisdiction', $input['addressJurisdiction'], $post_id);
        error_log("Updating address_jurisdiction: " . ($result ? 'success' : 'failed'));
    }
    
    // Handle Agency Name
    if (isset($input['agencyName'])) {
        $result = update_field('agency_name', $input['agencyName'], $post_id);
        error_log("Updating agency_name: " . ($result ? 'success' : 'failed'));
    }
    
    // Handle Phone Jurisdiction
    if (isset($input['phoneJurisdiction'])) {
        $result = update_field('phone_jurisdiction', $input['phoneJurisdiction'], $post_id);
        error_log("Updating phone_jurisdiction: " . ($result ? 'success' : 'failed'));
    }
    
    // Handle FIPS Code
    if (isset($input['fipsCode'])) {
        $result = update_field('fips_code', $input['fipsCode'], $post_id);
        error_log("Updating fips_code: " . ($result ? 'success' : 'failed'));
    }

    // Handle Fax Jurisdiction
    if (isset($input['faxJurisdiction'])) {
        $result = update_field('fax_jurisdiction', $input['faxJurisdiction'], $post_id);
        error_log("Updating fax_jurisdiction: " . ($result ? 'success' : 'failed'));
    }

    error_log('=== End Jurisdiction Update ===');
}, 10, 4);

// Handle the mutation data for jurisdiction creation
add_action('graphql_post_object_mutation_create_additional_data', function($post_id, $input, $post_type_object, $mutation_name) {
    error_log('=== Starting Jurisdiction Creation ===');
    error_log('Post ID: ' . $post_id);
    error_log('Input data: ' . print_r($input, true));

    // Handle Address Jurisdiction
    if (isset($input['addressJurisdiction'])) {
        $result = update_field('address_jurisdiction', $input['addressJurisdiction'], $post_id);
        error_log("Creating address_jurisdiction: " . ($result ? 'success' : 'failed'));
    }
    
    // Handle Agency Name
    if (isset($input['agencyName'])) {
        $result = update_field('agency_name', $input['agencyName'], $post_id);
        error_log("Creating agency_name: " . ($result ? 'success' : 'failed'));
    }
    
    // Handle Phone Jurisdiction
    if (isset($input['phoneJurisdiction'])) {
        $result = update_field('phone_jurisdiction', $input['phoneJurisdiction'], $post_id);
        error_log("Creating phone_jurisdiction: " . ($result ? 'success' : 'failed'));
    }
    
    // Handle FIPS Code
    if (isset($input['fipsCode'])) {
        $result = update_field('fips_code', $input['fipsCode'], $post_id);
        error_log("Creating fips_code: " . ($result ? 'success' : 'failed'));
    }

    // Handle Fax Jurisdiction
    if (isset($input['faxJurisdiction'])) {
        $result = update_field('fax_jurisdiction', $input['faxJurisdiction'], $post_id);
        error_log("Creating fax_jurisdiction: " . ($result ? 'success' : 'failed'));
    }

    error_log('=== End Jurisdiction Creation ===');
}, 10, 4);
