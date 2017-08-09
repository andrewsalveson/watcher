var urls = {};
urls.check = 'api.php/status';
urls.items = 'api.php/items';
var checks = {};

var getAll      = function(e){
  Object.keys(checks).map(getId);
};
var loadItems   = function(response){
  var results = response.results || [];
  for(var i = 0; i < results.length; i++){
    var item = results[i];
    $('body').append(`<div id="CHECK_${item}" class="check_line"></div>`);
    getId(item);
  }
};
var loadResults = function(response){
  var results = response.results || [];
  for(var i = 0; i < results.length; i++){
    var result = results[i];
    console.log(result);
    var id     = result.ID;
    checks[id] = result;
    console.log(`received id ${id}:`);
    var url    = result.URL;
    var htcode = result.HTTP_CODE;
    var status = result.STATUS;
    $(`#CHECK_${id}`).html(`<button class="refresh">refresh</button><span class="${status}"><span class="code code_${htcode}">${htcode}</span><a href="">${url}</a><span>`);
  }
};
var getId       = function(id){
  $(`#CHECK_${id}`).empty();
  $.ajax({
    url: `${urls.items}/${id}`,
    success: loadResults
  });
};

$.ajax({
  url: urls.items,
  success: loadItems
});

$(document).on('click','button.refresh',function(e){
  var id = $(e.target).parent().attr('id').split('_')[1];
  getId(id);
});
$(document).on('click','button.refresh_all',getAll);