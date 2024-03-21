function initDmsWrite() {
  var doWantChanged = function() {
    var want = $E('want').value;
    $E('file').parentNode.parentNode.style.display = want == 'file' ? null : 'none';
    $E('url').parentNode.parentNode.style.display = want == 'url' ? null : 'none';
  };
  $G('want').addEvent('change', doWantChanged);
  doWantChanged.call(this);
}
