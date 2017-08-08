var urls = {};
urls.check = 'api.php/status';
urls.items = 'api.php/items';
$(document).on('click','button.refresh',function(e){
  var id = $(e.target).parent().attr('id').split('_')[1];
  $(`#CHECK_${id}`).empty();
  getId(id);
});
$.ajax({
  url: urls.items,
  success: function(r){
    r = JSON.parse(r);
    for(var i = 0; i < r.results.length; i++){
      var item = r.results[i];
      $('body').append(`<div id="CHECK_${item}" class="check_line"></div>`);
      getId(item);
    }
  }
});
var getId = function(sid){
  console.log(`getting ID ${sid}`);
  $.ajax({
    url: `${urls.items}/${sid}`,
    success: function(r){
      r = JSON.parse(r);
      for(var i = 0; i < r.results.length; i++){
        var result = r.results[i];
        console.log(`received id ${sid}:`);
        console.log(result);
        var url    = result.URL;
        var htcode = result.HTTP_CODE;
        var status = result.STATUS;
        var id     = result.ID;
        $(`#CHECK_${id}`).html(`<button class="refresh">refresh</button><span class="${status}"><span class="code code_${htcode}">${htcode}</span><a href="">${url}</a><span>`);
      }
    }
  });
};