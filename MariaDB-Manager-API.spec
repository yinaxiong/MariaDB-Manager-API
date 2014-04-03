%define _topdir	 	%(echo $PWD)/
%define name		MariaDB-Manager-API
%define release         ##RELEASE_TAG##
%define version         ##VERSION_TAG##
%define install_path	/var/www/html/

BuildRoot:		%{buildroot}
BuildArch:		noarch
Summary: 		MariaDB Manager REST monitor and management API
License: 		GPL
Name: 			%{name}
Version: 		%{version}
Release: 		%{release}
Source: 		%{name}-%{version}-%{release}.tar.gz
Prefix: 		/
Group: 			Development/Tools
Requires:		php coreutils curl php-process php-pdo php-mysql php-mcrypt sshpass openssh openssh-clients gawk iproute at MariaDB-Manager-internalrepo
#BuildRequires:		

%description
MariaDB Manager is a tool to manage and monitor a set of MariaDB
servers using the Galera multi-master replication form Codership.
The API provides a RESTful interface to the underlying monitoring
and management functionality.
 
%prep

%setup -q

%build

%post

chown -R apache:apache /var/www
ln -s %{install_path}restfulapi/  %{install_path}/consoleAPI/api
timezone=`grep ZONE /etc/sysconfig/clock | sed 's/ZONE="\([^"]*\)"/\1/'`
sed -i "s|;date.timezone =|date.timezone = $timezone|" /etc/php.ini

mkdir -p /usr/local/skysql/cache/api
chown -R apache:apache /usr/local/skysql/cache
mkdir -p /var/www/.ssh
touch /var/www/.ssh/known_hosts
chown apache:apache /var/www/.ssh/known_hosts

mkdir -p /usr/local/skysql/SQLite/AdminConsole
chown -R apache:apache /usr/local/skysql/SQLite

mkdir -p /usr/local/skysql/config

if [ ! -f /var/www/.ssh/id_rsa.pub ] ; then
	ssh-keygen -q -f /var/www/.ssh/id_rsa -N "" 
	chown apache:apache /var/www/.ssh/id_rsa /var/www/.ssh/id_rsa.pub
fi

# Not overwriting existing API configurations
if [ ! -f /etc/skysqlmgr/api.ini ] ; then
	# Generating API key for the local scripts
	newKey=$(echo $RANDOM$(date)$RANDOM | md5sum | cut -f1 -d" ")
	
	componentID=2
	keyString="${componentID} = \"${newKey}\""

	# Registering key on components.ini file
	componentFile=/usr/local/skysql/config/components.ini
	grep "^${componentID} = \"" ${componentFile} &>/dev/null
	if [ "$?" != "0" ] ; then
		echo $keyString >> $componentFile
	fi
	
	# Registering key on api.ini file
	grep "^${componentID} = \"" /etc/skysqlmgr/api.ini.template &>/dev/null
	if [ "$?" != "0" ] ; then
		sed -i "/^\[apikeys\]$/a $keyString" /etc/skysqlmgr/api.ini.template
	fi

	# Generating API key for GREX
	newKey=$(echo $RANDOM$(date)$RANDOM | md5sum | cut -f1 -d" ")
	
	componentID=4
	keyString="${componentID} = \"${newKey}\""

	# Registering key on api.ini file
	grep "^${componentID} = \"" /etc/skysqlmgr/api.ini.template &>/dev/null
	if [ "$?" != "0" ] ; then
		sed -i "/^\[apikeys\]$/a $keyString" /etc/skysqlmgr/api.ini.template
	fi	

	# Creating api.ini file
	cp /etc/skysqlmgr/api.ini.template /etc/skysqlmgr/api.ini
fi

# disabling selinux! TO BE FIXED! 
echo 0 >/selinux/enforce
sed -i "s/SELINUX\s*=\s*enforcing/SELINUX=disabled/" /etc/selinux/config

# add firewall rule to allow port 80 
iptables -I INPUT -p tcp --dport 80 -j ACCEPT
service iptables save

service atd start
chkconfig atd on

%install
mkdir -p $RPM_BUILD_ROOT%{install_path}{consoleAPI,restfulapi,restfulapitest}
mkdir -p $RPM_BUILD_ROOT/etc/httpd/conf.d/

cp -R consoleAPI $RPM_BUILD_ROOT%{install_path}
#cp -R restfulapi/root/* $RPM_BUILD_ROOT/
mkdir -p $RPM_BUILD_ROOT/usr
cp -R restfulapi/root/usr/* $RPM_BUILD_ROOT/usr
mkdir -p $RPM_BUILD_ROOT/etc/skysqlmgr
cp -R restfulapi/root/etc/skysqlmgr/api.ini $RPM_BUILD_ROOT/etc/skysqlmgr/api.ini.template


cp -R restfulapi $RPM_BUILD_ROOT%{install_path}
cp -R restfulapitest/* $RPM_BUILD_ROOT%{install_path}restfulapi/
cp skysql_rewrite.conf $RPM_BUILD_ROOT/etc/httpd/conf.d/skysql_rewrite.conf
#mv $RPM_BUILD_ROOT/etc/skysqlmgr/api.ini $RPM_BUILD_ROOT/etc/skysqlmgr/api.ini.template 

%clean


%files
%defattr(-,root,root)
%{install_path}
%{install_path}consoleAPI/*
%{install_path}restfulapi/*
/etc/skysqlmgr/api.ini.template
/usr/local/skysql/scripts/api/*
/etc/httpd/conf.d/skysql_rewrite.conf

%changelog

