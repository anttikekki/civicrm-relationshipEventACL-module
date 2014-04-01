/**
* RelationshipEventACL extension admin screen logic. All HTML elements are created by this 
* JavaScript file. All data is queried and saved to server with Ajax so browser page is not refressed.
*/
cj(function ($) {
  'use strict';
  
  /*
  * ConfigRows in reloaded every time when configurations rows are created, edited or deleted.
  */
  var configRows;
  
  /**
  * Logic starting point. This is called when browser page is loaded.
  */
  function init() {
    reloadConfigAjax(initDataLoadComplete);
  }
  
  /**
  * Called when admin page init data has been loaded.
  * Saves init parameters and creates admin GUI controls.
  *
  * @param {object} result Ajax result
  */
  function initDataLoadComplete(result) {
    configRows = result;
    createGUI();
  }
  
  /**
  * Creates admin GUI HTML controls and inits DOM event listeners.
  */
  function createGUI() {
    var container = $('#relationshipEventACLAdminContainer');
    
    //Create & add HTML to DOM
    container.append(createAddButton());
    container.append(createEditForm());
    container.append(createConfigTableContainer());
    
    //Init event listeners
    $('#addNewButton').on('click', addButtonClicked);
    $('#saveFormButton').on('click', saveFormButtonClicked);
    $('#cancelButton').on('click', cancelButtonClicked);
    initTableEditLinkListeners();
  }
  
  /**
  * Init config rows table "Edit" and "Delete" click event listeners.
  * These needs to be recreated every time when table is reloaded & recreated.
  */
  function initTableEditLinkListeners() {
    var container = $('#relationshipEventACLAdminContainer');
    container.find('.edit_link').on('click', configEditLinkClicked);
    container.find('.delete_link').on('click', configDeleteLinkClicked);
  }
  
  /**
  * Create "Add" button and container HTML.
  *
  * @return {string} HTML
  */
  function createAddButton() {
    var html = '<div id="relationshipEventACLAdmin_addButtonContainer">';
    html += '<a class="button" href="#" id="addNewButton"><span><div class="icon add-icon"></div>Add</span></a>';
    html += '</div>';
    return html;
  }
  
  /**
  * Create configuration row edit & creation form HTML. Does not populate select-elements.
  *
  * @return {string} HTML
  */
  function createEditForm() {
    var html = '<div id="relationshipEventACLAdmin_editFormContainer" class="crm-block crm-form-block" style="display: none;">';
    html += '<form>';
    html += '<table class="form-layout-compressed">';
    html += '<tbody>';
    
    html += '<tr>';
    html += '<td class="label"><label for="config_key">Config Key<span class="crm-marker" title="This field is required.">*</span></label></td>';
    html += '<td id="config_key"></td>';
    html += '</tr>';
    
    html += '<tr>';
    html += '<td class="label"><label for="config_value">Config Value</label></td>';
    html += '<td><input id="config_value" class="form-text" name="config_value" size="40" maxlength="255"/></td>';
    html += '</tr>';
    
    html += '</tbody>';
    html += '</table>';
    
    html += '<div class="crm-submit-buttons">';
    html += '<a class="button" href="#" id="saveFormButton"><span>Save</span></a>';
    html += '<a class="button" href="#" id="cancelButton"><span>Cancel</span></a>';
    html += '</div>';
    
    html += '</form>';
    html += '</div>';
    return html;
  }
  
  /**
  * Create configuration table DIV-container and table inside of it.
  *
  * @return {string} HTML
  */
  function createConfigTableContainer() {
    var html = '<div id="relationshipEventACLAdmin_configTableContainer">';
    html += createConfigTable();
    html += '</div>';
    return html;
  }
  
  /**
  * Reloads all config rows from server with ajax. Calls callback parameter function 
  * with result JSON when loading is complete.
  *
  * @param {function} callback Callback function for Ajax
  */
  function reloadConfigAjax(callback) {
    $.ajax({
      dataType: "json",
      url: CRM.relationshipEventACL.getConfigAjaxURL,
      success: callback 
    });
  }
  
  /**
  * Update config table rows by first loading current situation from server with ajax and then 
  * recreating table HTML.
  */
  function updateConfigTable() {
    reloadConfigAjax(function(result) {
      configRows = result;
      $('#relationshipEventACLAdmin_configTableContainer').html(createConfigTable());
      initTableEditLinkListeners();
    });
  }
  
  /**
  * Create configuration table HTML with rows.
  *
  * @return {string} HTML
  */
  function createConfigTable() {
    var html = '<table class="selector row-highlight">';
    
    //Table header
    html += '<thead>';
    html += ' <tr>';
    html += '  <th>Config key</th>';
    html += '  <th>Config Value</th>';
    html += '  <th></th>';
    html += ' </tr>';
    html += '</thead>';
    
    //Table body
    html += '<tbody>';
    var index = 0;
    $.each(configRows, function(configKey, configValue) {
       html += createConfigTableRow(index++, configKey, configValue);
    });
    html += '</tbody';
    
    html += '</table>';
    return html;
  }
  
  /**
  * Create configuration table row HTML.
  *
  * @param {int} index Row index. Used to add 'even' and 'odd' classes for rows.
  * @param {string} configKey Configuration key.
  * @param {string} configValue Configuration value.
  * @return {string} HTML
  */
  function createConfigTableRow(index, configKey, configValue) {
    var rowClass = index % 2 === 0 ? 'even-row' : 'odd-row';
    var html = '<tr class="' + rowClass + '">';
    html += ' <td>' + configKey + '</td>';
    html += ' <td>' + configValue + '</td>';
    html += ' <td class="nowrap">';
    html += '  <a class="edit_link" href="#" data-config_key="' + configKey + '">Edit</a> | ';
    html += '  <a class="delete_link" href="#" data-config_key="' + configKey + '">Delete</a>';
    html += ' </td>';
    html+= '</tr>';
    return html;
  }
  
  /**
  * Called when configuration row edit link is clicked.
  * Shows edit form and populates form inputs. Saved current edit info to 'currentEdit' variable.
  *
  * @param {object} eventObject jQuery click event object
  */
  function configEditLinkClicked(eventObject) {
    showEditForm();
    
    //Get clicked row info from link data parameters
    var link = $(eventObject.target);
    var config_key = link.data().config_key;
    
    $('#config_key').text(config_key);
    $('#config_value').val(configRows[config_key]);
  }
  
  /**
  * Called when configuration row delete link is clicked.
  * Asks confirmation from user and does ajax call to server to do the deletion.
  * Updated configuration table after deletion.
  *
  * @param {object} eventObject jQuery click event object
  */
  function configDeleteLinkClicked(eventObject) {
    if(!confirm("Return to default?")) {
      return;
    }
  
    //Get clicked row info from link data parameters
    var link = $(eventObject.target);
    var data = {
      config_key: link.data().config_key
    };
  
    $.ajax({
      url: CRM.relationshipEventACL.deleteConfigRowAjaxURL,
      data: data,
      success: function() {
        updateConfigTable();
      }
    });
  }
  
  /**
  * Called when add new configuration row button is clicked.
  * Shows configuration editing row.
  *
  * @param {object} eventObject jQuery click event object
  */
  function addButtonClicked(eventObject) {
    showEditForm();
  }
  
  /**
  * Called when configuration row form save button is clicked.
  * Validates and saves row to server.
  *
  * @param {object} eventObject jQuery click event object
  */
  function saveFormButtonClicked(eventObject) {
    saveConfigForm();
  }
  
  /**
  * Called when configuration row editing form cancel button is clicked.
  * Hides editing form.
  *
  * @param {object} eventObject jQuery click event object
  */
  function cancelButtonClicked(eventObject) {
    hideEditForm();
  }
  
  /**
  * Shows configuration editing form.
  * Hides Add button and clears all old validation error messages.
  */
  function showEditForm() {
    $('#addNewButton').hide();
    $('#relationshipEventACLAdmin_editFormContainer').show();
    clearServerErrorMessage();
  }
  
  /**
  * Hides configuration editing form and shows Add button.
  */
  function hideEditForm() {
    $('#addNewButton').show();
    $('#relationshipEventACLAdmin_editFormContainer').hide();
  }
  
  /**
  * Save configuration edit form data to server.
  * Saving is done with ajax. Possible error message from server are displayed in form.
  * Form is closed only if saving was succesfull. Configuration table is reloaded and recreated after 
  * succesfull saving.
  */
  function saveConfigForm() {
    clearServerErrorMessage();

    $.ajax({
      url: CRM.relationshipEventACL.saveConfigRowAjaxURL,
      data: getFormData(),
      success: function(result) {
        if(result != 'ok') {
          showServerErrorMessage(result);
        }
        else {
          updateConfigTable();
          hideEditForm();
        }
      }
    });
  }
  
  /**
  * Get all configuration row form input data.
  *
  * @return {object} Form data
  */
  function getFormData() {
    return {
      config_key: $('#config_key').text(),
      config_value: $('#config_value').val()
    };
  }
  
  /**
  * Show form save error message from server in form.
  *
  * @param {string} message Error message
  */
  function showServerErrorMessage(message) {
    $('#relationshipEventACLAdmin_editFormContainer .crm-submit-buttons').append('<label class="crm-inline-error">' + message + '</label>');
  }
  
  /**
  * Removes possible server error message from edit form.
  */
  function clearServerErrorMessage() {
    $('#relationshipEventACLAdmin_editFormContainer .crm-submit-buttons .crm-inline-error').remove();
  }
  
  //Start logic
  init();
});