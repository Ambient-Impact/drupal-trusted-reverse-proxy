# Trusted Reverse Proxy

A simple module designed to run on sites that are known to operate in environment(s) behind known trusted reverse
proxies. This module presently performs a number of specific tasks:

* Inspecting `x-forwarded-for` headers to identify reverse proxies and trust the left-most IP found as the client IP.
  (For instance, you may be behind no or only one reverse proxy during local development but behind CloudFlare and a
  TLS-terminating reverse proxy and then Varnish in production.
* Demoting the status report/requirements error for a missing trusted host pattern setting to a "checked" finding.

Why a contrib module? This is complex enough a set of overrides that it is not easily accomplished in one or two
configuration changes, and hopefully this project provides a collection point for best practices on keeping Drupal a
best-in-class cloud native product by adopting sensible defaults in the cloud.

## Big giant red flag warning

This module is all about _trusting_ your upstream reverse proxies. If you don't trust them, don't use this module.
Furthermore, if you don't fully understand _why_ you would do such a thing, don't use this module.

Things to consider:

* Does your first-hop reverse proxy rewrite `x-forwarded-for` instead of passing through any headers received from the
  client request?
* Do your remaining hops on a private network, or otherwise restrict communication from only trusted reverse proxies?
* Do you understand HTTP mechanics sufficiently to understand the implications of implementing this module?
