dokuwiki-plugin-sqlcomp

Dokuwiki plugin to query SQL databases and show result as tables.
See documentation at https://www.dokuwiki.org/plugin:sqlcomp for more details on use.

Plugin adopted by Oliver Geisen on Aug 17th 2017. (Author was Christoph Lang)

NOTE: All users of versions prior to 2017 should move their alias-entries from sqlcomp/config.php
      into the new DokuWiki setting 'dbaliases'. Translate them like:
      OLD config.php => $sqlcomp['alias'] = "connectiondata";
      NEW setting    => alias="connectiondata"

