function setUserName (formid) {
	var form = document.forms[formid];
	var uri = 'user/' + form.elements['username'].value;
	setAction(formid, uri);
	setAuth(uri, form);
}
function setUser (formid) {
	var form = document.forms[formid];
	var uri = 'user';
	setAction(formid, uri);
	setAuth(uri, form);
}
function setAction (formid, uri) {
	document.getElementById(formid).action = locateAPI() + uri;
}
function setAuth (uri, form) {
	var d = new Date();
	form.elements['_authorization'].value = 'api-auth-' + localStorage.getItem("myKeyID") + '-' + hex_md5(uri + localStorage.getItem("myKey") + d);
	form.elements['_rfcdate'].value = encodeURIComponent(d);
}
function getPersistent (name, text) {
	var key = localStorage.getItem(name);
	if (key !== null) return key;
	do {
		key = prompt(text, '');
	}
	while (key === null);
	localStorage.setItem(name, key);
	return key;
}
function setup () {
	document.getElementById('showapikeyid').innerHTML = getPersistent("myKeyID", "Please enter API Key ID");
	document.getElementById('showapikey').innerHTML = getPersistent("myKey", "Please enter API Key");
}
window.onload = setup;
