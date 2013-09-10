%define _topdir	 	%(echo $PWD)/
%define name		admin_php
%define release         ##RELEASE_TAG##
%define version         ##VERSION_TAG##
%define buildroot %{_topdir}/%{name}-%{version}-%{release}root
%define install_path	/var/www/html/

BuildRoot:	%{buildroot}
Summary: 		Admin cnsole backend
License: 		GPL
Name: 			%{name}
Version: 		%{version}
Release: 		%{release}
Source: 		%{name}-%{version}-%{release}.tar.gz
Prefix: 		/
Group: 			Development/Tools
Requires:		php coreutils curl php-process php-pdo
#BuildRequires:		

%description
PHP sripts that implements admin console backend 

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
chown -R apache:apache usr/local/skysql/cache

%install
mkdir -p $RPM_BUILD_ROOT%{install_path}{consoleAPI,restfulapi,restfulapitest}
mkdir -p $RPM_BUILD_ROOT/etc/httpd/conf.d/

cp -R consoleAPI $RPM_BUILD_ROOT%{install_path}
cp -R restfulapi/root/* $RPM_BUILD_ROOT/
cp -R restfulapi $RPM_BUILD_ROOT%{install_path}
cp -R restfulapitest $RPM_BUILD_ROOT%{install_path}
cp skysql_rewrite.conf $RPM_BUILD_ROOT/etc/httpd/conf.d/skysql_rewrite.conf

%clean


%files
%defattr(-,root,root)
%{install_path}
%{install_path}consoleAPI/
%{install_path}consoleAPI/*
%{install_path}restfulapi/
%{install_path}restfulapi/*
%{install_path}restfulapitest/
%{install_path}restfulapitest/*
/etc/skysqlmgr/api.ini
/usr/local/skysql/scripts/api/*
/usr/local/skysql/scripts/api/steps/*
/etc/httpd/conf.d/skysql_rewrite.con

%changelog
* Wed Apr 17 2013 Timofey Turenko <timofey.turenko@skysql.com> - 0.1-3
- add postinst script to create log dir

* Wed Apr 03 2013 Timofey Turenko <timofey.turenko@skysql.com> - 0.1-2
- first packaged version

