
/* Feeling lib */

if (document.addEventListener)
  document.addEventListener('DOMContentLoaded', _snAutorunFeeling, false)
else window.onload = _snAutorunFeeling

function _snAutorunFeeling() {
  console.log('Feeling started');
  _snAddFeelingbutton();
  _snCheckAuthentication();
}

function _snAddFeelingbutton() {
  var siteRoot = _snGetCookieValueByName('site_root');
  var clientRoot = _snGetCookieValueByName('client_root');
  var siteId = _snGetCookieValueByName('site_id');

  var style = document.createElement("style");

  style.innerHTML = `
  .feeling_button_wrapper {
    position: fixed; inset-inline-end: -17px; top: 30px; z-index: 999; text-align: center; color: white;
  }
  .feeling_button_bg {
    border: grey solid 1px;
    border-top-left-radius: 80px; border-top-right-radius: 0px; border-bottom-left-radius: 80px;
    border-bottom-right-radius: 0px; background-color: white; cursor: pointer; display: block; width: 90px; height: 75px;
    transform: scale(0.6); margin-left: -20px; margin-top: -20px; box-shadow: 0px 5px 5px 1px gray;
  }
  .feeling_button_image {
    pointer-events: none; position: absolute; z-index: 999; width: 22px; backface-visibility: hidden; perspective: 1000px;
    line-height: 0; padding-top: 5px; opacity: 0.9; opacity: 0.9; inset-inline-start: 18px;
  }
  .feeling_button_arrow {
    position: absolute; z-index: 0; width: 40px; opacity: 1; inset-inline-start: 5px; top: 48px;
    font-size: 30px; line-height: 0; text-decoration: none;
    color: white; text-shadow: 1px 1px 1px grey;
  }
  .feeling_button_bg:hover { background-color: black; }
  .feeling_button_bg:active { background-color: lightgrey; top: 5px; transition: none;}
  .site_id_input {width: 99%;}
  .menu {
    visibility: hidden; position: fixed; inset-inline-start: 10px; top: 30px; z-index: 999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    font-size: 13px; color: #1f2933; background: #ffffff;
    width: 300px; max-width: 92vw; box-sizing: border-box;
    border: 1px solid #e3e8ef; border-radius: 10px;
    box-shadow: 0 10px 30px rgba(16, 24, 40, 0.18); overflow: hidden;
  }
  .menu .fm-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 12px; background: #243b53; color: #fff; font-weight: 600; letter-spacing: .02em;
  }
  .menu .fm-close { color: #fff; text-decoration: none; font-size: 15px; line-height: 1; padding: 2px 6px; border-radius: 4px; }
  .menu .fm-close:hover { background: rgba(255,255,255,0.18); }
  .menu .fm-section { padding: 8px 12px; border-top: 1px solid #eceff3; }
  .menu .fm-title { margin: 0 0 6px; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #7b8794; }
  .menu a.fm-link { display: block; color: #2b6cb0; text-decoration: none; padding: 3px 0; }
  .menu a.fm-link:hover { text-decoration: underline; }
  .menu .fm-pills { display: flex; gap: 6px; }
  .menu a.fm-pill {
    flex: 1; text-align: center; padding: 5px 0; border: 1px solid #cbd2d9; border-radius: 6px;
    color: #323f4b; text-decoration: none; font-size: 12px; background: #fff;
  }
  .menu a.fm-pill:hover { background: #f0f4f8; border-color: #aab4be; }
  .menu .fm-input {
    width: 100%; box-sizing: border-box; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    border: 1px solid #d9e0e8; border-radius: 6px; padding: 6px 8px; font-size: 12px; color: #323f4b; background: #f7f8fa;
  }
  .menu .fm-input:focus { outline: none; border-color: #2b6cb0; background: #fff; }
  .menu .fm-row { display: flex; align-items: center; gap: 6px; }
  .menu .fm-muted { color: #7b8794; }
  .menu .fm-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  `
  var div = document.createElement("div");
  div.setAttribute('class', 'feeling_button_wrapper');
  div.innerHTML = `
  <div class="feeling_button_image"><img src="/site_assets/img/logo.png" width="20"></div>

  <a class="feeling_button_arrow" href="javascript:_snShowHideElement('menu');">⇩</a>
  <a class="feeling_button_bg" href="`+ clientRoot + `sites" style="transform: matrix(0.6, 0, 0, 0.6, 0, 0);"></a>
  <div id="menu" class="menu">
    <div class="fm-head">
      <span>♡ Site Menu</span>
      <a class="fm-close" href="javascript:_snShowHideElement('menu');" title="Close">✕</a>
    </div>

    <div class="fm-section">
      <p class="fm-title">⚙ Administration</p>
      <a class="fm-link" href="`+ clientRoot + `admin/admin_sites/` + siteId + `">Site Administration →</a>
      <a class="fm-link" href="`+ clientRoot + `">Client Home</a>
    </div>

    <div class="fm-section">
      <p class="fm-title">🔗 Site</p>
      <a class="fm-link" href="`+ siteRoot + `">Site Root URL</a>
      <a class="fm-link" href="`+ siteRoot + `/sn_client_resources/site_api_docs/index.html">Site API Docs</a>
      <input class="fm-input fm-mono" readonly value="`+ siteRoot + `" onclick="this.select()">
    </div>

    <div class="fm-section">
      <p class="fm-title">👤 Account</p>
      <div class="fm-pills">
        <a class="fm-pill" href="`+ siteRoot + `login">Login</a>
        <a class="fm-pill" href="`+ siteRoot + `logout">Logout</a>
        <a class="fm-pill" href="`+ siteRoot + `register">Register</a>
      </div>
    </div>

    <div class="fm-section">
      <p class="fm-title">🆔 Site ID</p>
      <input class="fm-input fm-mono" readonly value="`+ siteId + `" onclick="this.select()">
      <div class="fm-row" style="margin-top:6px;">
        <span class="fm-muted">Authenticated:</span>
        <span id="is_authenticated">No</span>
      </div>
      <div id="visitor_id" class="fm-mono fm-muted" style="margin-top:4px; overflow:hidden; text-overflow:ellipsis;"></div>
    </div>
  </div>
  `
  document.body.appendChild(style);
  document.body.appendChild(div);
}

function _snShowHideElement(id) {
  if (document.getElementById(id).style.visibility == 'visible') {
    document.getElementById(id).style.visibility = 'hidden'
  } else document.getElementById(id).style.visibility = 'visible'
}

function _snGetCookieValueByName(name) {
  var match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
  return (match ? decodeURIComponent(match[3]) : null);
}

function _snCheckAuthentication() {
  const authElement = document.getElementById('is_authenticated');
  authElement.textContent = ''
  fetch('/site_api/v1/is_authenticated', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({})
  })
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        if (authElement) {
          authElement.textContent = data.data.is_authenticated ? 'Yes' : 'No';
        }
        const visitorElement = document.getElementById('visitor_id');
        if (visitorElement) {
          visitorElement.textContent = data.data.visitor_id;
        }
      }

    });
}


