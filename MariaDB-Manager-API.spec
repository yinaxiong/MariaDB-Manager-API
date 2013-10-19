%define _topdir	 	%(echo $PWD)/
%define name		MariaDB-Manager-API
%define release         ##RELEASE_TAG##
%define version         ##VERSION_TAG##
%define buildroot 	%{_topdir}/%{name}-%{version}-%{release}root
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
Requires:		php coreutils curl php-process php-pdo sshpass openssh openssh-clients gawk iproute at
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
mkdir -p /usr/local/skysql/log
chown apache:apache /usr/local/skysql/log
chown -R apache:apache /var/www
ln -s %{install_path}restfulapi/  %{install_path}/consoleAPI/api
timezone=`grep ZONE /etc/sysconfig/clock | sed 's/ZONE="\([^"]*\)"/\1/'`
sed -i "s|;date.timezone =|date.timezone = $timezone|" /etc/php.ini
touch /var/log/SDS.log
chown apache:apache /var/log/SDS.log
mkdir -p /usr/local/skysql/cache/api
chown -R apache:apache /usr/local/skysql/cache
mkdir -p /var/www/.ssh
touch /var/www/.ssh/known_hosts
chown apache:apache /var/www/.ssh/known_hosts
touch /var/log/skysql-test.log
chown apache:apache /var/log/skysql-test.log

if [ ! -f /var/www/.ssh/id_rsa.pub ] ; then
	ssh-keygen -q -f /var/www/.ssh/id_rsa -N "" 
	chown apache:apache /var/www/.ssh/id_rsa /var/www/.ssh/id_rsa.pub
fi

if [ ! -f /etc/skysqlmgr/api.ini ] ; then
	cp /etc/skysqlmgr/api.ini.template /etc/skysqlmgr/api.ini
fi


# disabling selinux! TO BE FIXED! 
echo 0 >/selinux/enforce
sed -i "s/SELINUX\s*=\s*enforcing/SELINUX=disabled/" /etc/selinux/config

# add firewall rule to allow port 80 
iptables -I INPUT -p tcp --dport 80 -j ACCEPT
service iptables save

service atq start
chkconfig atq on

%install
mkdir -p $RPM_BUILD_ROOT%{install_path}{consoleAPI,restfulapi,restfulapitest}
mkdir -p $RPM_BUILD_ROOT/etc/httpd/conf.d/

cp -R consoleAPI $RPM_BUILD_ROOT%{install_path}
cp -R restfulapi/root/* $RPM_BUILD_ROOT/
cp -R restfulapi $RPM_BUILD_ROOT%{install_path}
cp -R restfulapitest/* $RPM_BUILD_ROOT%{install_path}restfulapi/
cp skysql_rewrite.conf $RPM_BUILD_ROOT/etc/httpd/conf.d/skysql_rewrite.conf
mv $RPM_BUILD_ROOT/etc/skysqlmgr/api.ini $RPM_BUILD_ROOT/etc/skysqlmgr/api.ini.template 

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

