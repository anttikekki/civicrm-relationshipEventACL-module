/**
* Alter 'Find participants' search form to include 'limit=0' parameter in form target url. This 
* sets search result row limit to zero so all rows are returned. This is needed because pager do 
* not work anymore with relationshipEventACL row filtering.
*/
cj(function ($) {
  'use strict';
  
  var form = $('#Search');
  var action = form.attr('action');
  var noURLParameters = action.indexOf('?') === -1;
  action = action + (noURLParameters ? '?' : '&') + 'limit=0';
  form.attr('action', action);

});