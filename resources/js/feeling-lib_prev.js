
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
    1px solid grey; transform: scale(0.6); margin-left: -20px; margin-top: -20px; box-shadow: 0px 5px 5px 1px gray;
  }
  .feeling_button_image { 
    pointer-events: none; position: absolute; z-index: 999; width: 22px; backface-visibility: hidden; perspective: 1000px; 
    line-height: 0; padding-top: 5px; opacity: 0.9; opacity: 0.9; inset-inline-start: 18px; 
  }
  .feeling_button_arrow { 
    position: absolute; z-index: 0; width: 40px; opacity: 0; inset-inline-start: 20px; font-size: 30px; line-height: 0; 
    margin-top: 20px; opacity: 1; inset-inline-start: 5px; text-decoration: none; top: 30px; 
    color: white; text-shadow: 1px 1px 1px grey;
  }
  .feeling_button_bg:hover { background-color: black; }
  .feeling_button_bg:active { background-color: lightgrey; top: 5px; transition: none;}
  .site_id_input {width: 99%;}
  .menu {
    visibility:hidden; position: fixed; inset-inline-start: 10px; z-index: 999; background-color: white; padding: 10px; 
    text-align: start; font-size: medium; box-shadow: 1px 5px 5px 5px #c1c1c1; 
    color: black; border-radius: 5px; min-width: 300px;
  }
  `
  var div = document.createElement("div");
  div.setAttribute('class', 'feeling_button_wrapper');
  div.innerHTML = `
  <div class="feeling_button_image"><img src="/site_assets/img/logo.png" width="20"></div>

  <a class="feeling_button_arrow" href="javascript:_snShowHideElement('menu');">⇩</a>
  <a class="feeling_button_bg" href="`+ clientRoot + `sites" style="transform: matrix(0.6, 0, 0, 0.6, 0, 0);"></a>
  <div id="menu" class="menu">
  <a class="" href="javascript:_snShowHideElement('menu');" 
  style="text-decoration: none; padding-right: 0.4em;padding-left: 0.3em;">X</a>
  
  Site Menu
  <div>
  <a href="`+ clientRoot + `admin/admin_sites/` + siteId + `" style="text-decoration: underline;">Site Administration →</a>
  <br />
  <br />
  <a href="`+ clientRoot + `">Client</a>
  </div>
  <br />
  <div>
  <a href="`+ siteRoot + `">Site Root URL</a><br />  
  <a href="`+ siteRoot + `/sn_client_resources/site_api_docs/index.html">Site API</a><br />  
  <input readonly value="`+ siteRoot + `" style="width: 90%; overflow: hidden; text-overflow: ellipsis; 
      white-space: nowrap; border: 1px solid #ccc; padding: 8px; font-size: 16px;"
    onclick="this.select()">
  </div>
  <br />
  <div>
    <a href="`+ siteRoot + `login">Login</a>
    <a href="`+ siteRoot + `logout">Logout</a>
    <a href="`+ siteRoot + `register">Register</a>
  </div>
  <br />
  <div>
  <input  readonly value="`+ siteId + `" style="width: 90%; overflow: hidden; text-overflow: ellipsis; 
  white-space: nowrap; border: 1px solid #ccc; padding: 8px; font-size: 16px;" onclick="this.select()">
  <br />
  <br />
  <div>Authenticated: <span id="is_authenticated">No</span></div>
  
  <div id="visitor_id" style="width: 90%; overflow: hidden; text-overflow: ellipsis; "></div>
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


