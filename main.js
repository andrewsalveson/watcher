var check = 'api.php/status';
$.ajax({
  url: check,
  success:function(r){
    r = JSON.parse(r);
    for(var i = 0; i < r.results.length; i++){
      var result = r.results[i];
      var url = result.URL;
      $('body').append(`<div class="${result.STATUS}"><a href="${url}">${url}</a></div>`);
    }
  }
});