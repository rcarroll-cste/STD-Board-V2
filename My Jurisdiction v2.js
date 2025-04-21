$(document).ready(function () {
     
      // Get jurisdiction ID first, then initialize everything
      getCurrentUserJurisdictionId().then(jurisdictionId => {
          console.log("User jurisdiction ID:", jurisdictionId);
          initializeJurisdictionGrid(jurisdictionId);
      }).catch(error => {
          console.error("Failed to get jurisdiction ID:", error);
          // Initialize with default value or show error
          initializeJurisdictionGrid(0);
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
                                      fips_code: { type: "string", editable: true }
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
                  { field: "acf.fips_code", title: "FIPS Code" },
                  { field: "modified", title: "Last Updated", format: "{0:yyyy-MM-dd}" },
                  { command: ["edit"], title: "&nbsp;", width: "120px" }
              ],
              editable: "inline" 
          });
      }
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