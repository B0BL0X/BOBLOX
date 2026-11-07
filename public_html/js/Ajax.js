/*
 * BOBLOX.Ajax - a small XMLHttpRequest wrapper in the style of the 2006 ASP.NET AJAX
 * "PageMethods": it posts JSON to /WebServices/<Service>.asmx/<Method> and hands the
 * "d" part of the answer to your callback.
 *
 *   BOBLOX.Ajax.call('Character', 'WearItem', { itemId: 5 }, function (result) { ... }, function (message) { ... });
 */
var BOBLOX = window.BOBLOX || {};
window.BOBLOX = BOBLOX;

BOBLOX.Ajax = {
  createRequest: function () {
    if (window.XMLHttpRequest) return new XMLHttpRequest();
    try { return new ActiveXObject('Msxml2.XMLHTTP'); } catch (e) {}
    try { return new ActiveXObject('Microsoft.XMLHTTP'); } catch (e2) {}
    return null;
  },

  serviceUrl: function (service, method) {
    var c = BOBLOX.Config || { root: '/', friendlyUrls: true };
    return c.friendlyUrls
      ? c.root + 'WebServices/' + service + '.asmx/' + method
      : c.root + 'WebServices/' + service + '.php?method=' + method;
  },

  call: function (service, method, params, onSuccess, onFailure) {
    var xhr = BOBLOX.Ajax.createRequest();
    if (!xhr) { if (onFailure) onFailure('Your browser does not support AJAX.'); return; }
    xhr.open('POST', BOBLOX.Ajax.serviceUrl(service, method), true);
    xhr.setRequestHeader('Content-Type', 'application/json; charset=utf-8');
    xhr.setRequestHeader('X-CSRF-Token', (BOBLOX.Config && BOBLOX.Config.csrf) || '');
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      var data = null;
      try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
      if (xhr.status === 200 && data) {
        if (onSuccess) onSuccess(data.d);
      } else {
        var message = data && data.Message ? data.Message : 'Something went wrong (' + xhr.status + '). Please try again.';
        if (onFailure) onFailure(message, xhr.status);
        else alert(message);
      }
    };
    xhr.send(JSON.stringify(params || {}));
  }
};

// MS AJAX style shortcut
window.$get = window.$get || function (id) { return document.getElementById(id); };
