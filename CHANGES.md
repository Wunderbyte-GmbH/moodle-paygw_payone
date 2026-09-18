## Version v0.2.18 (2026091800)
* Bugfix: The provider exception is only re-thrown in developer mode. Before, every site with a debug level above NONE turned a hosted checkout the provider does not know any more into an uncaught exception. (Wunderbyte-GmbH/Wunderbyte-GmbH#2429)
