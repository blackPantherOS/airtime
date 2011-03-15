# ---
# definitions

%define python_sitepkgsdir %(echo `python -c "import sys; print (sys.prefix + '/lib/python' + sys.version[:3] + '/site-packages/')"`)
%define python_compile_opt python -O -c "import compileall; compileall.compile_dir('.')"
%define python_compile python -c "import compileall; compileall.compile_dir('.')"

%define ver     2.7.2
%define pkgver  2.7.2
%define pkgrel  1

# ---
# global settings

Packager: 	Kai Vehmanen <kvehmanen -at- eca -dot- cx>
Distribution: 	www.eca.cx audio software

# allow relocation
Prefix: 	%{_prefix}

# ---
# main package

Name: 		ecasound
Version: 	%{pkgver}
Release: 	%{pkgrel}
Serial: 	1
Copyright: 	GPL
Source: 	http://ecasound.seul.org/download/ecasound-%{ver}.tar.gz
URL: 		http://www.eca.cx/ecasound
BuildRoot:	/var/tmp/%{name}-%{version}-root-%(id -u -n)
Summary: 	ecasound - multitrack audio processing tool
Group: 		Applications/Sound

%description
Ecasound is a software package designed for multitrack audio
processing. It can be used for simple tasks like audio playback, 
recording and format conversions, as well as for multitrack effect 
processing, mixing, recording and signal recycling. Ecasound supports 
a wide range of audio inputs, outputs and effect algorithms. 
Effects and audio objects can be combined in various ways, and their
parameters can be controlled by operator objects like oscillators 
and MIDI-CCs. A versatile console mode user-interface is included 
in the package.

# ---
# library package - ecasound-devel

%package 	devel
Summary: 	Ecasound - development files
Group: 		Applications/Sound
	
%description devel
The ecasound-devel package contains the header files and static libraries
necessary for building apps like ecawave and ecamegapedal that
directly link against ecasound libraries.

# ---
# library package - libecasoundc

%package -n 	libecasoundc
Summary: 	Ecasound - libecasoundc
Group: 		Applications/Sound

requires: ecasound

%description -n libecasoundc
Ecasound - libecasoundc package. Provides 
C implementation of the Ecasound Control Interface
(ECI). Both static library files and and header 
files are included in the package.

# ---
# pyecasound

%package -n 	pyecasound
Summary: 	Python bindings to ecasound control interface.
Group: 		Applications/Sound
Requires: 	ecasound

%description -n pyecasound
Python bindings to Ecasound Control Interface (ECI).

# ---
# build phase

%prep
%setup -n ecasound-2.7.2
%build
./configure --prefix=%{_prefix} --libdir=%{_libdir} --mandir=%{_mandir} --with-pic $extra_params
make

%install
[ "$RPM_BUILD_ROOT" != "/" ] && rm -rf $RPM_BUILD_ROOT

make DESTDIR="$RPM_BUILD_ROOT" install-strip
( cd pyecasound
  %python_compile_opt
  %python_compile
  install *.pyc *.pyo $RPM_BUILD_ROOT%{python_sitepkgsdir}
)

# note! this is needed for automake 1.4 and older
strip --strip-unneeded \
	${RPM_BUILD_ROOT}%{_bindir}/ecasound \
	${RPM_BUILD_ROOT}%{_bindir}/ecaconvert \
	${RPM_BUILD_ROOT}%{_bindir}/ecafixdc \
	${RPM_BUILD_ROOT}%{_bindir}/ecanormalize \
	${RPM_BUILD_ROOT}%{_bindir}/ecaplay \
	${RPM_BUILD_ROOT}%{_bindir}/ecasignalview \
	${RPM_BUILD_ROOT}%{_libdir}/libecasound.a \
	${RPM_BUILD_ROOT}%{_libdir}/libecasoundc.a \
	${RPM_BUILD_ROOT}%{_libdir}/libkvutils.a

# ---
# cleanup after build

%clean
[ "$RPM_BUILD_ROOT" != "/" ] && rm -rf $RPM_BUILD_ROOT

# ---
# files sections (main)

%files
%defattr(-, root, root)
%doc NEWS COPYING COPYING.GPL COPYING.LGPL README INSTALL AUTHORS BUGS TODO examples
%doc Documentation/*.html
%doc Documentation/*.txt
%{_mandir}/man1/eca*
%{_mandir}/man5/eca*
%{_bindir}/ecasound
%{_bindir}/ecaconvert
%{_bindir}/ecafixdc
%{_bindir}/ecalength
%{_bindir}/ecamonitor
%{_bindir}/ecanormalize
%{_bindir}/ecaplay
%{_bindir}/ecasignalview
%dir %{_datadir}/ecasound
%{_datadir}/ecasound/ecasound.el
%config %{_datadir}/ecasound/ecasoundrc
%config %{_datadir}/ecasound/generic_oscillators
%config %{_datadir}/ecasound/effect_presets

# ---
# files sections (devel)

%files 		devel
%defattr(-, root, root)
%{_bindir}/libecasound-config
%{_includedir}/kvutils
%{_includedir}/libecasound
%{_libdir}/libecasound.la
%{_libdir}/libecasound.a
%{_libdir}/libkvutils.la
%{_libdir}/libkvutils.a

# ---
# files sections (libecasoundc)

%files -n 	libecasoundc
%defattr(-, root, root)
%{_bindir}/libecasoundc-config
%{_includedir}/libecasoundc
%{_libdir}/libecasoundc.la
%{_libdir}/libecasoundc.a

# ---
# files sections (pyecasound)

%files -n 	pyecasound
%defattr(644,root,root,755)
%attr(755,root,root) %{python_sitepkgsdir}/*.so
%{python_sitepkgsdir}/*.pyo
%{python_sitepkgsdir}/*.pyc
%{python_sitepkgsdir}/*.py

# ---
# changelog

%changelog
* Sun Jan 22 2006 Markus Grabner <grabner -at- icg -dot- tu-graz -dot- ac -dot- at>
- Updated to work on x86_64 platforms.
- The "--libdir=%{_libdir}" switch is required on x86_64 to select "lib64"
  for installation instead of "lib".
- "--with-pic" added to avoid compile errors on x86_64.
- "%{_bindir}/ecalength" added
- "%dir" keyword added to remove errors about files listed twice.

* Mon Apr 25 2005 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- fixed problems using ecasound.spec with recent versions of rpm
  by removing macro-statements from the changelog entries

* Wed Nov 03 2004 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- spam-protected all email-addresses

* Wed Aug 20 2003 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- added 'AUTHORS' file

* Mon Jan 20 2003 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- added Serial tag to differentiate between 2.2 pre and 
  final releases

* Sat Nov 02 2002 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- ported Fernando Lopez-Lezcano's changes to unify this
  spec file with PlanetCCRMA's ecasound package; see
  http://ccrma-www.stanford.edu/planetccrma/software/soundapps.html
- ecasound.el added to package (installed as a data
  file to avoid dependency to emacs/elisp)
- removed unnecessary raw documentation source files
- man files are no longer installed as doc files
- use redhat style mandir location 

* Thu Oct 31 2002 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- minor layout changes
- added TODO to the package
- changed to use rpmrc dir variables

* Thu Oct 24 2002 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- added the COPYING files to the package

* Thu Oct 17 2002 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- added the -devel package
- fixed the build procedure to handle static builds

* Wed Oct 16 2002 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- removed all shared libraries and subpackages containing 
  them
- ecamonitor binary added to main package

* Sat Oct 05 2002 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- changed libecasoundc versioning back to normal libtool style

* Thu Apr 25 2002 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- libraries put to separate subpackages, interface 
  version numbers code to library names
- ecasound-config renamed to libecasound-config
- ecasoundc-config renamed to libecasoundc-config
- plugin install dir changed from prefix/lib/ecasound-plugins
  to prefix/lib/libecasoundX-plugins
- 'contrib' directory removed
- ecasound-plugins subpackage renamed to libecasoundX-plugins

* Mon Oct 01 2001 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- dropped the hardcoded python module path from configure
  argument list

* Wed Jan 17 2001 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- python subpackage config (thanks to wrobell / PLD Linux!)

* Sat Dec 06 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- contrib and examples directories added to docs
- ecasoundc-config added
- libecasoundc added (C implementation of ECI)
- a new package: pyecasound

* Sat Nov 25 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- ecasignalview added to the package.

* Thu Aug 31 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- Added /etc/ld.so.conf modification script.
- Added DESTDIR to install-section.

* Wed Aug 30 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- 'ecasound-config' script added.

* Sun Aug 20 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- All Qt-related stuff removed.

* Wed Jul 06 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- Added the -plugins package.

* Wed Jun 07 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- ecaconvert added to the package.

* Mon Jun 05 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- Renamed ecatools programs.

* Mon Apr 15 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- Removed dynamic linking to ALSA libraries. You 
  can get ALSA support by recompiling the source-RPM
  package.

* Mon Feb 10 2000 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- Added libqtecasound to ecasound-qt.

* Mon Nov 09 1999 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- A complete reorganization. Ecasound distribution is now 
  divided to three RPMs: ecasound, ecasound-qt and ecasound-devel.

* Mon Nov 08 1999 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- As Redhat stopped the RHCN project, so these rpms 
  are again distributed via Redhat's contrib service
- You can also get these from http://ecasound.seul.org/download

* Sun Aug 15 1999 Kai Vehmanen <kvehmanen -at- eca -dot- cx>
- Initial rhcn release.
