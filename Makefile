all:

dst = debian/blabgen/etc/blabgen
web = debian/blabgen/usr/share/blabgen/web

install:

override_dh_install:
	dh_install

	./scripts/install_conf_card $(dst)/card.ini
	./scripts/install_conf $(dst)/config.ini
	./scripts/install_conf_admin $(dst)/admin.ini
	./scripts/remove_debug_lines $(web)/client/js/*.js

