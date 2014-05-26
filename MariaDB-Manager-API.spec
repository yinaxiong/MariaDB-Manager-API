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
Requires:		php coreutils curl php-process php-pdo php-mysql sshpass openssh openssh-clients gawk iproute at MariaDB-Manager-internalrepo rsync
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
sed -i '/^#[ ]*LoadModule[ ]*proxy_module/ {s/#//;}' /etc/httpd/conf/httpd.conf
sed -i '/^#[ ]*LoadModule[ ]*proxy_ajp_module/ {s/#//;}' /etc/httpd/conf/httpd.conf

mkdir -p /usr/local/skysql/cache/api
chown -R apache:apache /usr/local/skysql/cache
mkdir -p /usr/local/skysql/backups
chown -R apache:apache /usr/local/skysql/backups
mkdir -p /var/www/.ssh
touch /var/www/.ssh/known_hosts
chown apache:apache /var/www/.ssh/known_hosts

mkdir -p /usr/local/skysql/SQLite/AdminConsole
chown -R apache:apache /usr/local/skysql/SQLite

if [ ! -f /var/www/.ssh/id_rsa.pub ] ; then
	ssh-keygen -q -f /var/www/.ssh/id_rsa -N "" 
	chown apache:apache /var/www/.ssh/id_rsa /var/www/.ssh/id_rsa.pub
fi

# disabling selinux! TO BE FIXED! 
echo 0 >/selinux/enforce
sed -i "s/SELINUX\s*=\s*enforcing/SELINUX=disabled/" /etc/selinux/config
# configure selinux
chcon -Rv --type=httpd_sys_content_t /usr/local/skysql
setsebool httpd_can_network_connect true

# add firewall rule to allow port 80 
iptables -I INPUT -p tcp --dport 80 -j ACCEPT
service iptables save

if ! grep -q ^apache$ /etc/at.allow ; then
	echo apache >> /etc/at.allow
fi
service atd start
chkconfig atd on


%install
mkdir -p $RPM_BUILD_ROOT%{install_path}{consoleAPI,restfulapi,restfulapitest}
mkdir -p $RPM_BUILD_ROOT/etc/httpd/conf.d/

cp -R consoleAPI $RPM_BUILD_ROOT%{install_path}
#cp -R restfulapi/root/* $RPM_BUILD_ROOT/
mkdir -p $RPM_BUILD_ROOT/usr
cp -R restfulapi/root/usr/* $RPM_BUILD_ROOT/usr

cp -R restfulapi $RPM_BUILD_ROOT%{install_path}
cp -R restfulapitest/* $RPM_BUILD_ROOT%{install_path}restfulapi/
cp skysql_rewrite.conf $RPM_BUILD_ROOT/etc/httpd/conf.d/skysql_rewrite.conf


%clean


%files
%defattr(-,root,root)
%{install_path}
%{install_path}consoleAPI/*
%{install_path}restfulapi/*
/usr/local/skysql/scripts/api/*
/etc/httpd/conf.d/skysql_rewrite.conf

%changelog
