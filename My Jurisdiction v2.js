$(document).ready(function () {
     
    // --- ADD Fetching Roles ---
    var stdBaseUrl = "https://hivstiooj.cste.org/wp-json/wp/v2"; // Define base URL here for reuse
    Promise.all([
        // Fetch HIV Roles
        $.ajax({
            url: stdBaseUrl + '/hiv-role?_fields=id,name&per_page=100', // Adjust endpoint if needed
            method: 'GET',
            beforeSend: function(xhr) {
                if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            }
        }).then(data => {
            console.log("Fetched HIV Roles:", data);
            hivRolesData = data; // Store globally
            return data; // Pass data along if needed
        }).catch(error => {
            console.error("Failed to fetch HIV Roles:", error);
            // Provide default or empty array to prevent subsequent errors
            hivRolesData = []; 
            // Optionally notify the user
            // showNotification("Could not load HIV Roles.", "warning"); 
            return []; // Return empty array
        }),
        // Fetch STI Roles
        $.ajax({
            url: stdBaseUrl + '/sti-role?_fields=id,name&per_page=100', // Adjust endpoint if needed
            method: 'GET',
            beforeSend: function(xhr) {
                if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            }
        }).then(data => {
            console.log("Fetched STI Roles:", data);
            stiRolesData = data; // Store globally
            return data;
        }).catch(error => {
            console.error("Failed to fetch STI Roles:", error);
            stiRolesData = []; 
            // showNotification("Could not load STI Roles.", "warning");
            return [];
        })
    ]).then(() => {
        // Now that roles are fetched (or failed gracefully), get jurisdiction ID
        // --- END Fetching Roles ---

        // Get jurisdiction ID first, then initialize everything
        getCurrentUserJurisdictionId().then(jurisdictionId => {
            console.log("User jurisdiction ID:", jurisdictionId);
            initializeJurisdictionGrid(jurisdictionId);
            initializeUserGrid(jurisdictionId);
        }).catch(error => {
            console.error("Failed to get jurisdiction ID:", error);

        });
    }); 
   

    function initializeJurisdictionGrid(jurisdictionId) {
        var stdBaseUrl = "https://hivstiooj.cste.org/wp-json/wp/v2";

            jurisdictionDataSource = new kendo.data.DataSource({
                transport: {
                    read: {
                        // Request specific fields including the ACF object and full title object
                        url: stdBaseUrl + "/std_jurisdiction/" + jurisdictionId + "?_fields=id,title,modified,acf", 
                        dataType: "json"
                    },
                    update: {
                        url: stdBaseUrl + "/std_jurisdiction/"+ jurisdictionId,
                        method: "POST",
                        dataType: "json", 
                        contentType: "application/x-www-form-urlencoded", 
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                            console.log("Added nonce to update request:", wpApiSettings.nonce);
                        }
                    },
                    parameterMap: function(options, operation) {
                        if (operation === "update" || operation === "create") { 
                            
                            // --- MATCH STG LOGIC ---
                            // Construct the specific payload WordPress expects
                            var payload = {
                                // Directly access the 'rendered' property of the title object
                                title: options.title ? options.title.rendered : '', 
                                // Send ACF fields nested under 'acf'
                                acf: options.acf 
                            };
                            // --- End Match ---
                            
                            console.log("Update/Create operation payload (specific fields):", payload);
                            var dataToSend = $.param(payload);
                            console.log("Raw Request Body (Corrected):", dataToSend);
                            return dataToSend;
                        }
                        return undefined; 
                    }
                },
                schema: {
                    model: {
                        id: "id",
                        fields: {
                            id: { editable: false, nullable: true },
                            title: { 
                                defaultValue: { rendered: "" },
                                editable: true 
                                
                            },
                            // --- End Match ---
                            modified: { editable: false, type: "date" },
                            acf: { 
                                type: "object", 
                                defaultValue: {}, 
                                editable: true,   
                                fields: {
                                    agency_name: { type: "string", editable: true },
                                    address_jurisdiction: { type: "string", editable: true },
                                    phone_jurisdiction: { type: "string", editable: true },
                                   
                                }
                            }
                        }
                    },
                    parse: function(response) {
                        if (response && typeof response === 'object' && !Array.isArray(response)) {
                            if (!response.acf) {
                              console.warn("ACF object missing in API read response:", response); 
                              response.acf = {}; 
                            } else {
                              console.log("ACF object received from API:", response.acf);
                            }
                            // --- Ensure title object exists for schema ---
                            if (!response.title || typeof response.title.rendered === 'undefined') {
                              console.warn("Title object or title.rendered missing in API read response:", response);
                              response.title = { rendered: "" }; // Ensure object structure
                            }
                            // --- End Ensure ---
                            console.log("Parsing single object response for Kendo:", response);
                            return [response]; 
                        }
                        console.log("Parsing API response (not a single object):", response);
                        return response; 
                    }
                }
            });

        $("#jurisdictionGrid").kendoGrid({
            dataSource: jurisdictionDataSource,
            pageable: true,
            height: 550,
            toolbar: ["save", "cancel"], 
            columns: [
                { field: "title.rendered", title: "Jurisdiction Name" }, 
                { field: "acf.agency_name", title: "Agency Name" }, 
                { field: "acf.address_jurisdiction", title: "Address" },
                { field: "acf.phone_jurisdiction", title: "Phone" },
              
                { field: "modified", title: "Last Updated", format: "{0:yyyy-MM-dd}" },
                { command: ["edit"], title: "&nbsp;", width: "120px" }
            ],
            editable: "inline" 
        });
    }

    function initializeUserGrid(jurisdictionId) {

        var stdBaseUrl = "https://hivstiooj.cste.org/wp-json/wp/v2";
        var usersDataSource = new kendo.data.DataSource({
            transport: {
                read: {
                    // Fetch users, include ACF fields
                    url: stdBaseUrl + "/users?_fields=id,first_name,last_name,email,acf&context=edit&acf_user_jurisdiction=" + jurisdictionId, // Make sure acf is requested
                    dataType: "json",
                    beforeSend: function(xhr) {
                         if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
                            xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                        } else {
                            console.error("Read Users: wpApiSettings.nonce is missing!");
                        }
                    }
                },
                update: {
                    url: function(options) {
                        console.log("Update URL for user ID:", options.id);
                        return stdBaseUrl + "/users/" + options.id;
                    },
                    method: "POST",
                    dataType: "json",
                    contentType: "application/json; charset=utf-8", 
                    beforeSend: function(xhr) {
                        if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
                            xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                        } else {
                            console.error("Update User: wpApiSettings.nonce is missing!");
                        }
                    }
                },
                // --- START ADD: Create Transport ---
                create: {
                    url: stdBaseUrl + "/users", // Base endpoint for creating users
                    method: "POST",
                    dataType: "json",
                    contentType: "application/json; charset=utf-8",
                    beforeSend: function(xhr) {
                        if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
                            xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                        } else {
                            console.error("Create User: wpApiSettings.nonce is missing!");
                        }
                    }
                },
                // --- END ADD: Create Transport ---

                // --- START ADD: Destroy Transport ---
                destroy: {
                    url: function(options) { 
                        // Need user ID and force=true to bypass trash
                        console.log("Destroy URL for user ID:", options.id);
                        return stdBaseUrl + "/users/" + options.id + "?force=true&reassign=1"; 
                    },
                    method: "DELETE",
                    dataType: "json", // Expect JSON response (might be minimal on success)
                    beforeSend: function(xhr) {
                        if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
                            xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                        } else {
                            console.error("Destroy User: wpApiSettings.nonce is missing!");
                        }
                    }
                },
                // --- END ADD: Destroy Transport ---

                // --- Modify parameterMap ---
                parameterMap: function(options, operation) {
                    if (operation === "update") {
                        // Construct payload including ACF fields
                        var payload = {
                            first_name: options.first_name,
                            last_name: options.last_name,
                            email: options.email,
                            // Include ACF fields in the payload
                            acf: {
                               user_phone: options.acf.user_phone || "",
                               user_fax: options.acf.user_fax || "",
                               notes_sti_hiv: options.acf.notes_sti_hiv || "",
                               // Send role IDs as an array
                               hiv_role: options.acf.hiv_role || [],
                               sti_role: options.acf.sti_role || [],
                               // --- ADD Missing Required Field ---
                               user_jurisdiction: jurisdictionId
                               // --- END ADD ---
                            }
                        };
                        console.log("Update User Payload:", payload);
                        return JSON.stringify(payload);
                    }
                    // --- ADD Create Payload Handling ---
                    else if (operation === "create") {
                        // WordPress requires username, email, password
                        // Generate username (e.g., from email) and password
                        const username = options.email ? options.email.split('@')[0].replace(/[^a-zA-Z0-9._-]/g, "") : "new_user_" + Date.now();
                        const password = generateSecurePassword(); // We'll add this helper function

                        var createPayload = {
                            username: username,
                            email: options.email,
                            password: password,
                            first_name: options.first_name || "", // Use defaults if empty
                            last_name: options.last_name || "",
                            roles: ["subscriber"], // Or assign a default relevant role
                            acf: {
                               user_jurisdiction: jurisdictionId, // Assign current jurisdiction
                               user_phone: options.acf.user_phone || "",
                               user_fax: options.acf.user_fax || "",
                               notes_sti_hiv: options.acf.notes_sti_hiv || "",
                               hiv_role: options.acf.hiv_role || [],
                               sti_role: options.acf.sti_role || []
                            }
                        };
                        console.log("Create User Payload:", createPayload);
                        return JSON.stringify(createPayload);
                    }
                    // --- END ADD ---
                    
                    // For read/destroy (DELETE has URL params handled in transport.destroy.url)
                    return options;
                }
            },
            schema: {
                model: {
                    id: "id", 
                    fields: {
                        id: { editable: false, nullable: true },
                        first_name: { type: "string", validation: { required: true } },
                        last_name: { type: "string", validation: { required: true } },
                        email: { type: "string", validation: { required: true, email: true } },
                        // Define the ACF object and its nested fields
                        acf: { 
                            type: "object", 
                            defaultValue: { // Provide defaults for nested fields
                                user_phone: "",
                                user_fax: "",
                                notes_sti_hiv: "",
                                hiv_role: [],
                                sti_role: []
                            }, 
                            editable: true, // Mark ACF as editable
                            // Define nested fields for clarity (optional but good practice)
                            fields: {
                                user_phone: { type: "string", editable: true },
                                user_fax: { type: "string", editable: true },
                                notes_sti_hiv: { type: "string", editable: true },
                                hiv_role: {
                                    editable: true,
                                    defaultValue: [],
                                    validation: {
                                        required: { message: "HIV Role is Required" }
                                    }
                                },
                                sti_role: {
                                    editable: true,
                                    defaultValue: [],
                                    validation: {
                                        required: { message: "STI Role is Required" }
                                    }
                                }
                            } 
                        }
                    }
                },
                // Parse ACF data correctly if it exists
                parse: function(response) {
                    console.log("Parsing user response:", response);
                    // Ensure each user object has an 'acf' object, even if empty
                    if (Array.isArray(response)) {
                        response.forEach(user => {
                            if (!user.acf) {
                                user.acf = { // Initialize default acf structure if missing
                                  user_phone: "",
                                  user_fax: "",
                                  notes_sti_hiv: "",
                                  hiv_role: [],
                                  sti_role: []
                                }; 
                            } else {
                                // Ensure role fields exist and are arrays
                                if (!Array.isArray(user.acf.hiv_role)) user.acf.hiv_role = [];
                                if (!Array.isArray(user.acf.sti_role)) user.acf.sti_role = [];
                            }
                        });
                    }
                    return response; 
                },
                 error: function(e) {
                    console.error("DataSource error in Users Grid:", e);
                    var grid = $("#usersGrid").data("kendoGrid");
                     if (grid) {
                         grid.cancelChanges();
                     }
                }
            },

        });

        $("#userGrid").kendoGrid({
            dataSource: usersDataSource,
            pageable: true,
            height: 550,
            toolbar: ["create", "save", "cancel"],
            columns: [
                { field: "first_name", title: "First Name", width: "130px" },
                { field: "last_name", title: "Last Name", width: "130px" },
                { field: "email", title: "Email", width: "180px" },
                // --- ADD New Columns ---
                { 
                    field: "acf.user_phone", 
                    title: "Phone", 
                    width: "130px",
                    template: function(dataItem) {
                        const rawValue = dataItem.acf?.user_phone;
                        if (typeof rawValue === 'string' || typeof rawValue === 'number') {
                            const digits = String(rawValue).replace(/\D/g, ''); // Remove non-digits
                            if (digits.length === 10) {
                                return `(${digits.substring(0, 3)}) ${digits.substring(3, 6)}-${digits.substring(6, 10)}`;
                            }
                            return digits; // Return raw digits if not 10 digits long
                        }
                        return "—"; // Return placeholder if empty or invalid
                    },
                    editor: function(container, options) {
                        $('<input data-bind="value:' + options.field + '"/>')
                            .appendTo(container)
                            .kendoNumericTextBox({
                                format: "0", // Format for whole numbers without commas
                                decimals: 0,
                                spinners: false // Disable up/down arrows
                            });
                    } 
                },
                { 
                    field: "acf.user_fax", 
                    title: "Fax", 
                    width: "130px",
                    template: function(dataItem) {
                        const rawValue = dataItem.acf?.user_fax;
                        if (typeof rawValue === 'string' || typeof rawValue === 'number') {
                            const digits = String(rawValue).replace(/\D/g, ''); // Remove non-digits
                            if (digits.length === 10) {
                                return `(${digits.substring(0, 3)}) ${digits.substring(3, 6)}-${digits.substring(6, 10)}`;
                            }
                            return digits; // Return raw digits if not 10 digits long
                        }
                        return "—"; // Return placeholder if empty or invalid
                    },
                    editor: function(container, options) {
                        $('<input data-bind="value:' + options.field + '"/>')
                            .appendTo(container)
                            .kendoNumericTextBox({
                                format: "0", // Format for whole numbers without commas
                                decimals: 0,
                                spinners: false // Disable up/down arrows
                            });
                    } 
                },
                { 
                    field: "acf.hiv_role", 
                    title: "HIV Roles", 
                    width: "180px",
                    template: function(dataItem) { // Display role names
                        if (!dataItem.acf || !dataItem.acf.hiv_role || dataItem.acf.hiv_role.length === 0) return "—";
                        return dataItem.acf.hiv_role.map(roleId => {
                            // Ensure hivRolesData is available and is an array
                            if (!Array.isArray(hivRolesData)) return `ID: ${roleId}`; 
                            const role = hivRolesData.find(r => r.id === roleId);
                            return role ? role.name : `ID: ${roleId}`;
                        }).join(', ');
                    },
                    editor: hivRoleEditor // Use custom editor
                },
                { 
                    field: "acf.sti_role", 
                    title: "STI Roles", 
                    width: "180px",
                    template: function(dataItem) { // Display role names
                        if (!dataItem.acf || !dataItem.acf.sti_role || dataItem.acf.sti_role.length === 0) return "—";
                         return dataItem.acf.sti_role.map(roleId => {
                            // Ensure stiRolesData is available and is an array
                            if (!Array.isArray(stiRolesData)) return `ID: ${roleId}`;
                            const role = stiRolesData.find(r => r.id === roleId);
                            return role ? role.name : `ID: ${roleId}`;
                        }).join(', ');
                    },
                    editor: stiRoleEditor // Use custom editor
                },
                { field: "acf.notes_sti_hiv", title: "Notes", width: "200px" },
                // --- END ADD ---
                { command: ["edit", "destroy"], title: "&nbsp;", width: "180px" } 
            ],
            editable: "inline"
        });

    }

    // --- ADD Custom Role Editors ---
    function hivRoleEditor(container, options) {
        $('<input name="acf.hiv_role" />')
            .appendTo(container)
            .kendoMultiSelect({
                dataTextField: "name",
                dataValueField: "id",
                dataSource: hivRolesData, 
                value: options.model.acf.hiv_role || [], 
                valuePrimitive: true, 
                autoClose: false,
                change: function(e) {
                   options.model.set(options.field, this.value());
                   console.log(options.field + " updated:", this.value());
                }
            });
    }

    function stiRoleEditor(container, options) {
        $('<input name="acf.sti_role" />')
            .appendTo(container)
            .kendoMultiSelect({
                dataTextField: "name",
                dataValueField: "id",
                dataSource: stiRolesData, 
                value: options.model.acf.sti_role || [],
                valuePrimitive: true, 
                autoClose: false,
                change: function(e) {
                   options.model.set(options.field, this.value());
                   console.log(options.field + " updated:", this.value());
                }
            });
    }
    // --- END ADD ---

});

function getCurrentUserJurisdictionId() {
    return new Promise((resolve, reject) => {
        // Check only for nonce, as root is not provided by the specified PHP snippet
        if (typeof wpApiSettings === 'undefined' || typeof wpApiSettings.nonce === 'undefined') {
            const errorMsg = "Error: wpApiSettings or wpApiSettings.nonce is not defined. Ensure the nonce script runs in the header.";
            console.error(errorMsg);
            // Reject the promise immediately
            reject(new Error(errorMsg));
            return; // Stop execution of the function
        }

        // Hardcode the full URL since wpApiSettings.root is not available
        const apiUrl = 'https://hivstiooj.cste.org/wp-json/wp/v2/users/me';

        $.ajax({
            url: apiUrl, 
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            }
        }).done(function(userData) {
            // Try to parse the ID directly
            const jurisdictionId = parseInt(userData?.acf?.user_jurisdiction, 10);
            // Resolve with the ID (or 0 if NaN/missing)
            console.log("Fetched user data, jurisdiction ID from ACF:", jurisdictionId);
            resolve(isNaN(jurisdictionId) ? 0 : jurisdictionId);
        }).fail(function(jqXHR, textStatus, errorThrown) {
            // On failure, log error and reject with the error
            console.error(`Error fetching user data via REST API (${apiUrl}): ${textStatus}`, errorThrown, jqXHR.responseText);
            reject(new Error(`Failed to fetch user data: ${textStatus}`)); // Reject with a proper Error object
        });
    });
}

// --- ADD Helper Function for Password Generation ---
function generateSecurePassword(length = 12) {
    const upperChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lowerChars = 'abcdefghijklmnopqrstuvwxyz';
    const numbers = '0123456789';
    // Make sure special chars are safe for typical password fields
    const specialChars = '!@#$%^&*()_+[]{}|;:,.<>?'; 
    
    const allChars = upperChars + lowerChars + numbers + specialChars;
    let password = '';
    
    // Ensure at least one of each required type
    password += upperChars.charAt(Math.floor(Math.random() * upperChars.length));
    password += lowerChars.charAt(Math.floor(Math.random() * lowerChars.length));
    password += numbers.charAt(Math.floor(Math.random() * numbers.length));
    password += specialChars.charAt(Math.floor(Math.random() * specialChars.length));
    
    // Fill the rest
    for (let i = password.length; i < length; i++) {
        password += allChars.charAt(Math.floor(Math.random() * allChars.length));
    }
    
    // Shuffle the characters to avoid predictable patterns
    password = password.split('').sort(() => 0.5 - Math.random()).join('');
    
    console.log("Generated temporary password."); // Avoid logging the actual password
    return password;
}
// --- END ADD ---

// --- ADD Helper functions for OOJ Grid ---

// Generic function to extract database IDs (ensure it's defined or moved here)
// ... (keep extractIds as is) ...

// Generic template factory functions
function createTaxonomyTemplate(fieldName) {
    return function(dataItem) {
        const items = dataItem[fieldName];
        if (Array.isArray(items) && items.length > 0) {
            // If only one item (likely from single select), just show its name
            if (items.length === 1) {
                return (items[0] && items[0].name) ? items[0].name : 'Invalid Item';
            }
            // Otherwise (for multi-select), join names with a comma
            return items.map(item => (item && item.name) ? item.name : 'Invalid Item').join(', ');
        }
        return '—'; // Return em dash if no data or not an array
    };
}

// ... (keep createPointOfContactsTemplate as is) ...
// ... (keep createSingleSelectEditor as is) ...
// ... (keep createMultiSelectEditor as is) ...

// Specific editor functions for each taxonomy/relationship
function createOojInfectionEditor(container, options) {
    // This already correctly uses createSingleSelectEditor
    createSingleSelectEditor(container, options, {
        fieldName: "oojInfections",
        dataSource: oojInfectionsData,
        placeholder: "Select Infection..."
    });
}

function createOojActivitiesEditor(container, options) {
    // CHANGE: Use createSingleSelectEditor instead of createMultiSelectEditor
    createSingleSelectEditor(container, options, {
        fieldName: "oojActivities",
        dataSource: oojActivitiesData,
        placeholder: "Select Activity..." // Adjusted placeholder
    });
}

// ... (keep createMethodsOfTransmittingEditor as is - assuming it's multi-select) ...
// ... (keep createAcceptableForPiisEditor as is - assuming it's multi-select) ...
// ... (keep createPointOfContactsEditor as is - already single select) ...

// --- END ADD Helper functions ---


// --- ADD initializeOOJGrid Function ---
function initializeOOJGrid(jurisdictionId) {
    var oojDataSource = new kendo.data.DataSource({
        // ... (keep transport and parameterMap as is) ...
        schema: {
            model: {
                id: "id", // The WordPress Post ID
                fields: {
                    id: { editable: false, nullable: true },
                    title: { type: "string", defaultValue: "OOJ Detail" },
                    oojInfections: { defaultValue: [] },
                    oojActivities: { defaultValue: [] },
                    // CHANGE: lastDateOfExposure back to string
                    lastDateOfExposure: { type: "string" },
                    dispositionsReturned: { type: "string" },
                    acceptAndInvestigate: { type: "string" },
                    methodsOfTransmitting: { defaultValue: [] }, // Assuming multi-select
                    acceptableForPiis: { defaultValue: [] },    // Assuming multi-select
                    pointOfContacts: {
                        defaultValue: [],
                        validation: { required: { message: "Point of Contact is required" } }
                    },
                    notes: { type: "string" }
                }
            },
            // ... (keep parse and error functions as is) ...
        }, // End schema
         // ... (keep change function as is) ...
    }); // End oojDataSource

    // OOJ Grid Initialization
    $("#OojGrid").kendoGrid({
        dataSource: oojDataSource,
        // ... (keep general grid options like height, scrollable, toolbar, editable, save) ...
         save: function(e) {
            console.log("OOJ Grid save event triggered for model:", e.model);
            var validator = e.container.find("[data-role=validator]").data("kendoValidator"); // Find validator within the container
             if (validator && !validator.validate()) {
                console.error("OOJ Grid: Validation failed. Preventing save.");
                e.preventDefault(); // Prevent the sync/transport call
            } else {
                console.log("OOJ Grid: Validation passed. Proceeding with save.");
                // Allow default save behavior
            }
        },
        columns: [
             {
                field: "oojInfections", title: "OOJ Infections", width: 180,
                // Add a custom attribute for potentially more descriptive tooltip content later
                headerAttributes: { "data-tooltip-content": "Select the primary infection type related to the Out-of-Jurisdiction case." },
                template: createTaxonomyTemplate("oojInfections"), // Template handles single/multi
                editor: createOojInfectionEditor // Uses single-select editor
            },
            {
                field: "oojActivities", title: "OOJ Activities", width: 180,
                 headerAttributes: { "data-tooltip-content": "Select the activity or activities performed for this case." },
                template: createTaxonomyTemplate("oojActivities"), // Template handles single/multi
                editor: createOojActivitiesEditor // NOW uses single-select editor
            },
            {
                 field: "lastDateOfExposure", title: "Last Exposure Dt", width: 150,
                 headerAttributes: { "data-tooltip-content": "Enter the estimated last date of exposure relevant to the case." }
                 // REMOVED: format: "{0:MM/dd/yyyy}"
             },
            {
                field: "dispositionsReturned", title: "Dispositions Returned", width: 160,
                headerAttributes: { "data-tooltip-content": "Description for Dispositions Returned." } // Add more descriptions
            },
            {
                field: "acceptAndInvestigate", title: "Accept & Investigate", width: 160,
                 headerAttributes: { "data-tooltip-content": "Description for Accept & Investigate." }
             },
            {
                field: "methodsOfTransmitting", title: "Transmission Methods", width: 180,
                 headerAttributes: { "data-tooltip-content": "Select the method(s) used for transmitting information." },
                template: createTaxonomyTemplate("methodsOfTransmitting"), // Template handles single/multi
                editor: createMethodsOfTransmittingEditor // Assumed multi-select
            },
            {
                field: "acceptableForPiis", title: "Acceptable PII", width: 180,
                headerAttributes: { "data-tooltip-content": "Select the types of Personally Identifiable Information acceptable for transmission." },
                template: createTaxonomyTemplate("acceptableForPiis"), // Template handles single/multi
                editor: createAcceptableForPiisEditor // Assumed multi-select
            },
            {
                field: "pointOfContacts", title: "Point of Contact", width: 180,
                 headerAttributes: { "data-tooltip-content": "Select the primary point of contact within your jurisdiction for this case." },
                template: createPointOfContactsTemplate(), // Handles single POC
                editor: createPointOfContactsEditor // Uses single-select editor
            },
            {
                field: "notes", title: "Notes", width: 200,
                headerAttributes: { "data-tooltip-content": "Enter any relevant notes for this OOJ case." }
            },
            { command: ["edit", "destroy"], title: "&nbsp;", width: "180px" }
        ]
    }); // End kendoGrid initialization

    // --- ADD Tooltip Initialization for Headers ---
    $("#ooj_grid .k-grid-header").kendoTooltip({
        // Filter to target the header cells that have a data-field attribute
        filter: "th[data-field]",
        // Position the tooltip below the header
        position: "bottom",
        // Define the content dynamically based on the target header
        content: function(e) {
            // e.target is the jQuery object for the specific <th> element
            var target = e.target;
            // Retrieve the custom tooltip content from the data attribute
            var tooltipContent = target.data("tooltip-content");
            // Fallback to the title if no custom content is set
            return tooltipContent || target.data("title") || "No description";
        },
        // Optional: Adjust width, add animation, etc.
        width: 200,
        showAfter: 750 // Show after a slight delay (milliseconds)
    });
    console.log("Kendo Tooltip initialized for OOJ grid headers.");
    // --- END Tooltip Initialization ---

} // --- END initializeOOJGrid Function ---