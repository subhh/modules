/*global Hunt, VuFind */
/*exported checkItemStatuses, itemStatusFail */

function linkCallnumbers(callnumber, callnumber_handler) {
  if (callnumber_handler) {
    var cns = callnumber.split(',\t');
    for (var i = 0; i < cns.length; i++) {
      cns[i] = '<a href="' + VuFind.path + '/Alphabrowse/Home?source=' + encodeURI(callnumber_handler) + '&amp;from=' + encodeURI(cns[i]) + '">' + cns[i] + '</a>';
    }
    return cns.join(',\t');
  }
  return callnumber;
}

function displayArticleStatus(results, $item) {
  $item.removeClass('js-item-pending');
  $item.find('.ajax-availability').removeClass('ajax-availability hidden');
  $item.find('.status').empty();
  $.each(results, function(index, result){
    if (typeof(result.error) != 'undefined'
      && result.error.length > 0
    ) {
      $item.find('.status').append('error');
    } else {
      if (typeof(result.href) != 'undefined') {
        var html = '<a href="' + result.href + '" class="' + result.level + '" title="' + result.label + '" target="_blank">' + VuFind.translate(result.label) + '</a><br/>';
        $item.find('.status').append(html);
      }
    }
  });
}

function displayItemStatus(result, $item) {
  $item.removeClass('js-item-pending');
  $item.find('.status').empty().append(result.availability_message);
  $item.find('.ajax-availability').removeClass('ajax-availability hidden');
  if (typeof(result.error) != 'undefined'
    && result.error.length > 0
  ) {
    // Only show error message if we also have a status indicator active:
    if ($item.find('.status').length > 0) {
      $item.find('.callnumAndLocation').empty().addClass('text-danger').append(result.error);
    } else {
      $item.find('.callnumAndLocation').addClass('hidden');
    }
    $item.find('.callnumber,.hideIfDetailed,.location').addClass('hidden');
  } else if (typeof(result.full_status) != 'undefined'
    && result.full_status.length > 0
    && $item.find('.callnumAndLocation').length > 0
  ) {
    // Full status mode is on -- display the HTML and hide extraneous junk:
    $item.find('.callnumAndLocation').empty().append(result.full_status);
    $item.find('.callnumber,.hideIfDetailed,.location,.status').addClass('hidden');
  } else if (typeof(result.missing_data) != 'undefined'
    && result.missing_data
  ) {
    // No data is available -- hide the entire status area:
    // $item.find('.callnumAndLocation,.status').addClass('hidden');
    var errorText = VuFind.translate('daia_missing_data');
    //var errorText = VuFind.translate('error_occurred');
	  
    $item.find('.status').empty().append(errorText);
  } else if (result.locationList) {
    // We have multiple locations -- build appropriate HTML and hide unwanted labels:
    $item.find('.callnumber,.hideIfDetailed,.location').addClass('hidden');
    var locationListHTML = "";
    for (var x = 0; x < result.locationList.length; x++) {
      locationListHTML += '<div class="groupLocation">';
      if (result.locationList[x].availability) {
        locationListHTML += '<span class="text-success"><i class="fa fa-ok" aria-hidden="true"></i> '
          + result.locationList[x].location + '</span> ';
      } else if (typeof(result.locationList[x].status_unknown) !== 'undefined'
          && result.locationList[x].status_unknown
      ) {
        if (result.locationList[x].location) {
          locationListHTML += '<span class="text-warning"><i class="fa fa-status-unknown" aria-hidden="true"></i> '
            + result.locationList[x].location + '</span> ';
        }
      } else {
        locationListHTML += '<span class="text-danger"><i class="fa fa-remove" aria-hidden="true"></i> '
          + result.locationList[x].location + '</span> ';
      }
      locationListHTML += '</div>';
      locationListHTML += '<div class="groupCallnumber">';
      locationListHTML += (result.locationList[x].callnumbers)
        ? linkCallnumbers(result.locationList[x].callnumbers, result.locationList[x].callnumber_handler) : '';
      locationListHTML += '</div>';
    }
    $item.find('.locationDetails').removeClass('hidden');
    $item.find('.locationDetails').html(locationListHTML);
  } else {
    // Default case -- load call number and location into appropriate containers:
    $item.find('.callnumber').empty().append(linkCallnumbers(result.callnumber, result.callnumber_handler) + '<br/>');
    $item.find('.location').empty().append(
      result.reserve === 'true'
        ? result.reserve_message
        : result.location
    );
  }
  if (typeof(result.daiaplus) != 'undefined' && result.daiaplus.length > 0) {
    $item.find('.callnumAndLocation').addClass('hidden');
    $item.find('.status').empty().append(result.daiaplus);
    $item.find('.status').removeClass('hidden');
  }
}
function itemStatusFail(response, textStatus) {
  if (textStatus === 'abort' || typeof response.responseJSON === 'undefined') {
    return;
  }
  // display the error message on each of the ajax status place holder
  $('.js-item-pending .callnumAndLocation').addClass('text-danger').empty().removeClass('hidden')
    .append(typeof response.responseJSON.data === 'string' ? response.responseJSON.data : VuFind.translate('error_occurred'));
}

var itemStatusIds = [];
var itemStatusEls = {};
var itemStatusTimer = null;
var itemStatusDelay = 200;
var itemStatusRunning = false;
var itemStatusList = false;
var itemStatusSource = '';
var itemStatusHideLink = '';
var itemStatusType = '';
var itemStatusRanks = [];

function runItemAjaxForQueue() {
  // Only run one item status AJAX request at a time:
  if (itemStatusRunning) {
    itemStatusTimer = setTimeout(runItemAjaxForQueue, itemStatusDelay);
    return;
  }
  itemStatusRunning = true;
  if (itemStatusSource == 'Search2' || itemStatusType == 'electronic') {
    var method = 'getArticleStatuses';
  } else {
    var method = 'getItemStatuses';
  }
  for (var i=0; i<itemStatusIds.length; i++) {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=' + method,
      dataType: 'json',
      method: 'get',
      data: {id:[itemStatusIds[i]], list:itemStatusList, source:itemStatusSource, hideLink:itemStatusHideLink, rank:itemStatusRanks[i]}
    })
    .done(function checkItemStatusDone(response) {
      for (var j = 0; j < response.data.statuses.length; j++) {
        var status = response.data.statuses[j];
        if (method == 'getItemStatuses') {
          displayItemStatus(status, itemStatusEls[status.id]);
        } else {
          displayArticleStatus(status, itemStatusEls[status.id]);
        }
        itemStatusIds.splice(itemStatusIds.indexOf(status.id), 1);
        itemStatusRanks.splice(itemStatusIds.indexOf(status.id), 1);
      }
      itemStatusRunning = false;
    })
    .fail(function checkItemStatusFail(response, textStatus) {
      itemStatusFail(response, textStatus);
      itemStatusRunning = false;
    });
  }
}

function itemQueueAjax(id, rank, el) {
  if (el.hasClass('js-item-pending')) {
    return;
  }
  clearTimeout(itemStatusTimer);
  itemStatusIds.push(id);
  itemStatusRanks.push(rank);
  itemStatusEls[id] = el;
  itemStatusTimer = setTimeout(runItemAjaxForQueue, itemStatusDelay);
  el.addClass('js-item-pending').removeClass('hidden');
  el.find('.callnumAndLocation').removeClass('hidden');
  el.find('.callnumAndLocation .ajax-availability').removeClass('hidden');
  el.find('.status').removeClass('hidden');
}

//Listenansicht
function checkItemStatus(el) {
  var $item = $(el);
  var id = $item.attr('data-id');
  var rank = $item.attr('data-rank');
  itemStatusSource = $item.attr('data-src');
  itemStatusList = ($item.attr('data-list') == 1);
  itemStatusHideLink = $item.attr('data-hide-link');
  itemStatusType = $item.attr('data-type');
  itemQueueAjax(id + '', rank + '', $item);
}

var itemStatusObserver = null;

function checkItemStatuses(_container) {
  var container = typeof _container === 'undefined'
    ? document.body
    : _container;

  var availabilityItems = $(container).find('.availabilityItem');
  for (var i = 0; i < availabilityItems.length; i++) {
    var id = $(availabilityItems[i]).attr('data-id');
    var rank = $(availabilityItems[i]).attr('data-rank');
    itemStatusSource = $(availabilityItems[i]).attr('data-src');
    itemStatusList = ($(availabilityItems[i]).attr('data-list') == 1);
    itemStatusHideLink = $(availabilityItems[i]).attr('data-hide-link');
    itemStatusType = $(availabilityItems[i]).attr('data-type');
    itemQueueAjax(id + '', rank + '', $(availabilityItems[i]));
  }
  // Stop looking for a scroll loader
  if (itemStatusObserver) {
    itemStatusObserver.disconnect();
  }
}
$(document).ready(function() {
  function checkItemStatusReady() {
    if (typeof Hunt === 'undefined') {
      checkItemStatuses();
    } else {
      itemStatusObserver = new Hunt(
        $('.availabilityItem').toArray(),
        { enter: checkItemStatus }
      );
    }
  }
  checkItemStatusReady();
});

function initDaiaPlusOverlay () {
    $('.daiaplus-overlay').on('click', function(e){
        e.preventDefault();
        $('#modal .modal-body').html($('#'+$(this).data('daiaplus-overlay')).html());
        VuFind.modal('show');
    });
}
