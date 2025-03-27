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
            'confidentialPhone'  => ['type' => 'String'],
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
            if (isset($input['confidentialPhone'])) {
                update_field('confidential_phone', $input['confidentialPhone'], 'user_' . $user_id);
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

    // User Role Management Mutation
    register_graphql_mutation('updateUserRoles', [
        'inputFields' => [
            'userId' => [
                'type' => 'ID',
                'description' => __('Global Relay ID of the User to update.', 'your-textdomain'),
            ],
            'jurisdictionId' => [
                'type' => 'Int',
                'description' => __('The jurisdiction ID', 'your-textdomain'),
            ],
            'roles' => [
                'type' => ['list_of' => 'String'],
                'description' => __('List of roles', 'your-textdomain'),
            ],
            'oojroles' => [
                'type' => ['list_of' => 'String'],
                'description' => __('List of OOJ roles', 'your-textdomain'),
            ],
            'contacts' => [
                'type' => ['list_of' => 'Int'],
                'description' => __('List of contact IDs', 'your-textdomain'),
            ],
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

            // Update roles if provided
            if (isset($input['roles']) && is_array($input['roles'])) {
                // Handle roles update logic here
                // This depends on how roles are stored in your system
                update_field('roles', $input['roles'], 'user_' . $user_id);
            }

            // Update OOJ roles if provided
            if (isset($input['oojroles']) && is_array($input['oojroles'])) {
                // Handle OOJ roles update logic
                update_field('oojroles', $input['oojroles'], 'user_' . $user_id);
            }

            // Update contacts if provided
            if (isset($input['contacts']) && is_array($input['contacts'])) {
                // Handle contacts update logic
                update_field('contacts', $input['contacts'], 'user_' . $user_id);
            }

            // Update jurisdiction if provided
            if (isset($input['jurisdictionId'])) {
                update_field('user_jurisdiction', $input['jurisdictionId'], 'user_' . $user_id);
            }

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
     * Register the CreateOOJDetail mutation
     */
    register_graphql_mutation('createOOJDetail', [
        'inputFields' => [
            'title' => [
                'type' => 'String',
                'description' => __('Title for the OOJ Detail.', 'your-textdomain'),
            ],
            'status' => [
                'type' => 'PostStatusEnum',
                'description' => __('Status of the OOJ Detail', 'your-textdomain'),
            ],
            'jurisdictionSelection' => [
                'type' => 'Int',
                'description' => __('The jurisdiction selection (std_jurisdiction post ID)', 'your-textdomain'),
            ],
            'lastDateOfExposure' => [
                'type' => 'String',
                'description' => __('Last date of exposure', 'your-textdomain'),
            ],
            'dispositionsReturned' => [
                'type' => 'String',
                'description' => __('Dispositions returned', 'your-textdomain'),
            ],
            'acceptAndInvestigate' => [
                'type' => 'String',
                'description' => __('Accept and investigate', 'your-textdomain'),
            ],
            'notes' => [
                'type' => 'String',
                'description' => __('Notes for this OOJ detail', 'your-textdomain'),
            ],
            'pointOfContacts' => [
                'type' => ['list_of' => 'Int'],
                'description' => __('Point of contacts user IDs', 'your-textdomain'),
            ],
            'oOJInfections' => [
                'type' => 'Int',
                'description' => __('OOJ infection term ID', 'your-textdomain'),
            ],
            'oOJActivities' => [
                'type' => ['list_of' => 'Int'],
                'description' => __('OOJ activity term IDs', 'your-textdomain'),
            ],
            'methodsOfTransmitting' => [
                'type' => ['list_of' => 'Int'],
                'description' => __('Method of transmitting term IDs', 'your-textdomain'),
            ],
            'acceptableForPiis' => [
                'type' => ['list_of' => 'Int'],
                'description' => __('Acceptable for PII term IDs', 'your-textdomain'),
            ],
        ],
        'outputFields' => [
            'oojDetail' => [
                'type' => 'OOJDetail',
                'description' => __('The created OOJ Detail', 'your-textdomain'),
                'resolve' => function($payload, $args, $context, $info) {
                    if (!empty($payload['id'])) {
                        return get_post($payload['id']);
                    }
                    return null;
                }
            ],
        ],
        'mutateAndGetPayload' => function($input, $context, $info) {
            // Create the OOJ Detail post
            $post_args = [
                'post_type' => 'ooj-detail',
                'post_title' => !empty($input['title']) ? $input['title'] : 'OOJ Detail',
                'post_status' => !empty($input['status']) ? $input['status'] : 'publish',
            ];
            
            $post_id = wp_insert_post($post_args);
            
            if (is_wp_error($post_id)) {
                throw new \GraphQL\Error\UserError($post_id->get_error_message());
            }
            
            // Set taxonomies and ACF fields
            if (!empty($input['oOJInfections'])) {
                wp_set_object_terms($post_id, [$input['oOJInfections']], 'acf-ooj-infection');
            }
            
            if (!empty($input['oOJActivities'])) {
                wp_set_object_terms($post_id, $input['oOJActivities'], 'acf-ooj-activity');
            }
            
            if (!empty($input['methodsOfTransmitting'])) {
                wp_set_object_terms($post_id, $input['methodsOfTransmitting'], 'iccr_method-of-transmitting');
            }
            
            if (!empty($input['acceptableForPiis'])) {
                wp_set_object_terms($post_id, $input['acceptableForPiis'], 'acceptable-for-pii');
            }
            
            // Update ACF fields
            if (isset($input['jurisdictionSelection'])) {
                update_field('jurisdiction_selection', $input['jurisdictionSelection'], $post_id);
            }
            
            if (isset($input['lastDateOfExposure'])) {
                update_field('last_date_of_exposure', $input['lastDateOfExposure'], $post_id);
            }
            
            if (isset($input['dispositionsReturned'])) {
                update_field('dispositions_returned', $input['dispositionsReturned'], $post_id);
            }
            
            if (isset($input['acceptAndInvestigate'])) {
                update_field('accept_and_investigate', $input['acceptAndInvestigate'], $post_id);
            }
            
            if (isset($input['notes'])) {
                update_field('notes', $input['notes'], $post_id);
            }
            
            if (isset($input['pointOfContacts'])) {
                update_field('point_of_contacts', $input['pointOfContacts'], $post_id);
            }
            
            return [
                'id' => $post_id,
            ];
        }
    ]);
    
    /**
     * Register the UpdateOOJDetail mutation
     */
    register_graphql_mutation('updateOOJDetail', [
        'inputFields' => [
            'id' => [
                'type' => ['non_null' => 'ID'],
                'description' => __('ID of the OOJ Detail to update', 'your-textdomain'),
            ],
            'title' => [
                'type' => 'String',
                'description' => __('Title for the OOJ Detail.', 'your-textdomain'),
            ],
            'status' => [
                'type' => 'PostStatusEnum',
                'description' => __('Status of the OOJ Detail', 'your-textdomain'),
            ],
            'jurisdictionSelection' => [
                'type' => 'Int',
                'description' => __('The jurisdiction selection (std_jurisdiction post ID)', 'your-textdomain'),
            ],
            'lastDateOfExposure' => [
                'type' => 'String',
                'description' => __('Last date of exposure', 'your-textdomain'),
            ],
            'dispositionsReturned' => [
                'type' => 'String',
                'description' => __('Dispositions returned', 'your-textdomain'),
            ],
            'acceptAndInvestigate' => [
                'type' => 'String',
                'description' => __('Accept and investigate', 'your-textdomain'),
            ],
            'notes' => [
                'type' => 'String',
                'description' => __('Notes for this OOJ detail', 'your-textdomain'),
            ],
            'pointOfContacts' => [
                'type' => ['list_of' => 'Int'],
                'description' => __('Point of contacts user IDs', 'your-textdomain'),
            ],
            'oOJInfections' => [
                'type' => 'Int',
                'description' => __('OOJ infection term ID', 'your-textdomain'),
            ],
            'oOJActivities' => [
                'type' => ['list_of' => 'Int'],
                'description' => __('OOJ activity term IDs', 'your-textdomain'),
            ],
            'methodsOfTransmitting' => [
                'type' => ['list_of' => 'Int'],
                'description' => __('Method of transmitting term IDs', 'your-textdomain'),
            ],
            'acceptableForPiis' => [
                'type' => ['list_of' => 'Int'],
                'description' => __('Acceptable for PII term IDs', 'your-textdomain'),
            ],
        ],
        'outputFields' => [
            'oojDetail' => [
                'type' => 'OOJDetail',
                'description' => __('The updated OOJ Detail', 'your-textdomain'),
                'resolve' => function($payload, $args, $context, $info) {
                    if (!empty($payload['id'])) {
                        return get_post($payload['id']);
                    }
                    return null;
                }
            ],
        ],
        'mutateAndGetPayload' => function($input, $context, $info) {
            // Get the post ID from the global ID
            $post_id = \WPGraphQL\Utils\Utils::get_database_id_from_id($input['id'], 'oOJDetail');
            
            if (!$post_id || get_post_type($post_id) !== 'ooj-detail') {
                throw new \GraphQL\Error\UserError('Invalid OOJ Detail ID.');
            }
            
            // Update post title/status if provided
            $post_args = [
                'ID' => $post_id,
            ];
            
            if (!empty($input['title'])) {
                $post_args['post_title'] = $input['title'];
            }
            
            if (!empty($input['status'])) {
                $post_args['post_status'] = $input['status'];
            }
            
            if (count($post_args) > 1) {
                wp_update_post($post_args);
            }
            
            // Update taxonomies
            if (isset($input['oOJInfections'])) {
                wp_set_object_terms($post_id, [$input['oOJInfections']], 'acf-ooj-infection');
            }
            
            if (isset($input['oOJActivities'])) {
                wp_set_object_terms($post_id, $input['oOJActivities'], 'acf-ooj-activity');
            }
            
            if (isset($input['methodsOfTransmitting'])) {
                wp_set_object_terms($post_id, $input['methodsOfTransmitting'], 'iccr_method-of-transmitting');
            }
            
            if (isset($input['acceptableForPiis'])) {
                wp_set_object_terms($post_id, $input['acceptableForPiis'], 'acceptable-for-pii');
            }
            
            // Update ACF fields
            if (isset($input['jurisdictionSelection'])) {
                update_field('jurisdiction_selection', $input['jurisdictionSelection'], $post_id);
            }
            
            if (isset($input['lastDateOfExposure'])) {
                update_field('last_date_of_exposure', $input['lastDateOfExposure'], $post_id);
            }
            
            if (isset($input['dispositionsReturned'])) {
                update_field('dispositions_returned', $input['dispositionsReturned'], $post_id);
            }
            
            if (isset($input['acceptAndInvestigate'])) {
                update_field('accept_and_investigate', $input['acceptAndInvestigate'], $post_id);
            }
            
            if (isset($input['notes'])) {
                update_field('notes', $input['notes'], $post_id);
            }
            
            if (isset($input['pointOfContacts'])) {
                update_field('point_of_contacts', $input['pointOfContacts'], $post_id);
            }
            
            return [
                'id' => $post_id,
            ];
        }
    ]);

    /**
     * Register the DeleteOOJDetail mutation
     */
    register_graphql_mutation('deleteOOJDetail', [
        'inputFields' => [
            'id' => [
                'type' => ['non_null' => 'ID'],
                'description' => __('ID of the OOJ Detail to delete', 'your-textdomain'),
            ],
            'forceDelete' => [
                'type' => 'Boolean',
                'description' => __('Whether to permanently delete or move to trash', 'your-textdomain'),
            ],
        ],
        'outputFields' => [
            'deletedId' => [
                'type' => 'ID',
                'description' => __('The ID of the deleted OOJ Detail', 'your-textdomain'),
                'resolve' => function($payload) {
                    return $payload['deletedId'] ?? null;
                }
            ],
            'success' => [
                'type' => 'Boolean',
                'description' => __('Whether the deletion was successful', 'your-textdomain'),
                'resolve' => function($payload) {
                    return $payload['success'] ?? false;
                }
            ],
        ],
        'mutateAndGetPayload' => function($input, $context, $info) {
            // Get the post ID from the global ID
            $post_id = \WPGraphQL\Utils\Utils::get_database_id_from_id($input['id'], 'oOJDetail');
            
            if (!$post_id || get_post_type($post_id) !== 'ooj-detail') {
                throw new \GraphQL\Error\UserError('Invalid OOJ Detail ID.');
            }
            
            // Force delete or trash based on input
            $force_delete = isset($input['forceDelete']) ? (bool) $input['forceDelete'] : false;
            $result = wp_delete_post($post_id, $force_delete);
            
            return [
                'deletedId' => $input['id'],
                'success' => (bool) $result,
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
