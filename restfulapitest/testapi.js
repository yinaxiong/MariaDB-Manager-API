function setFields () {
	var form = document.forms[arguments[0]];
	var uri = '';
	for (i = 1; i < arguments.length; i+=2) {
		uri = uri + '/' + arguments[i];
		if (i+1 < arguments.length) {
			uri = uri + '/' + form.elements[arguments[i+1]].value;
		}
	}
	form.action = getPersistent("myLink") + uri;
	setAuth(uri.substring(1), form);
	return true;
}
function setAuth (uri, form) {
	var d = new Date();
	form.elements['_authorization'].value = 'api-auth-' + localStorage.getItem("myKeyID") + '-' + hex_md5(uri + localStorage.getItem("myKey") + d);
	form.elements['_rfcdate'].value = encodeURIComponent(d);
}
function getPersistent (name) {
	var key = localStorage.getItem(name);
	if (key == null) key = resetPersistent(name);
	return key;
}
function resetPersistent (name, id) {
	if (name === "myKeyID") text = "Please enter API Key ID\n(which is an integer)";
	else if (name === "myKey") text = "Please enter API Key\n(which is a long string)";
	else if (name === "myLink") text = "Please enter location to send requests\nwith no trailing slash\ne.g. http://eng01.skysql.com/consoleAPI/api";
	else text = "Please enter a value for " + name;
	do {
		key = prompt(text);
	}
	while (key == null);
	localStorage.setItem(name, key);
	if (id != null) document.getElementById(id).innerHTML = key;
	return key;
}
function setup () {
	document.getElementById('showapikeyid').innerHTML = getPersistent("myKeyID");
	document.getElementById('showapikey').innerHTML = getPersistent("myKey");
	document.getElementById('showlink').innerHTML = getPersistent("myLink");
}
window.onload = setup;
