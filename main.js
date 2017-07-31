var check = 'api.php/status';
$.ajax({
  url: check,
  success:function(r){
    r = JSON.parse(r);
    for(var i = 0; i < r.results.length; i++){
      var result = r.results[i];
      var url    = result.URL;
      var htcode = result.HTTP_CODE;
      var status = result.STATUS;
      $('body').append(`<div class="${status}">
        <span class="code code_${htcode}">${htcode}</span>
        <a href="${url}">${url}</a></div>
      `);
    }
  }
});