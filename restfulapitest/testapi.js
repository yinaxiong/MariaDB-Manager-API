function setFields () {
	var form = document.forms[arguments[0]];
	var uri = '';
	for (i = 1; i < arguments.length; i+=2) {
		uri = uri + '/' + arguments[i];
		if (i+1 < arguments.length) {
			uri = uri + '/' + encodeURIComponent(form.elements[arguments[i+1]].value);
		}
	}
	form.action = getPersistent("myLink") + uri;
	setAuth(uri.substring(1), form);
	return true;
}
function setName (id, name) {
	var element = document.getElementById(id);
	element.name = name;
	element.classList.add("hasdata");
}
function unsetName (id) {
	var element = document.getElementById(id);
	element.name = "";
	element.classList.remove("hasdata");
}
function setAuth (uri, form) {
	var oDate = new Date();
	var d = GetRFC822Date(oDate);
	form.elements['_authorization'].value = 'api-auth-' + localStorage.getItem("myKeyID") + '-' + hex_md5(uri.replace(/\/+$/, "") + localStorage.getItem("myKey") + d);
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
  /*Accepts a Javascript Date object as the parameter;
  outputs an RFC822-formatted datetime string. */
  function GetRFC822Date(oDate)
  {
    var aMonths = new Array("Jan", "Feb", "Mar", "Apr", "May", "Jun", 
                            "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
    
    var aDays = new Array( "Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat");
    var dtm = new String();
			
    dtm = aDays[oDate.getDay()] + ", ";
    dtm += padWithZero(oDate.getDate()) + " ";
    dtm += aMonths[oDate.getMonth()] + " ";
    dtm += oDate.getFullYear() + " ";
    dtm += padWithZero(oDate.getHours()) + ":";
    dtm += padWithZero(oDate.getMinutes()) + ":";
    dtm += padWithZero(oDate.getSeconds()) + " " ;
    dtm += getTZOString(oDate.getTimezoneOffset());
    return dtm;
  }
  //Pads numbers with a preceding 0 if the number is less than 10.
  function padWithZero(val)
  {
    if (parseInt(val) < 10)
    {
      return "0" + val;
    }
    return val;
  }

  /* accepts the client's time zone offset from GMT in minutes as a parameter.
  returns the timezone offset in the format [+|-}DDDD */
  function getTZOString(timezoneOffset)
  {
    var hours = Math.floor(timezoneOffset/60);
    var modMin = Math.abs(timezoneOffset%60);
    var s = new String();
    s += (hours > 0) ? "-" : "+";
    var absHours = Math.abs(hours)
    s += (absHours < 10) ? "0" + absHours :absHours;
    s += ((modMin == 0) ? "00" : modMin);
    return(s);
  }
window.onload = setup;
