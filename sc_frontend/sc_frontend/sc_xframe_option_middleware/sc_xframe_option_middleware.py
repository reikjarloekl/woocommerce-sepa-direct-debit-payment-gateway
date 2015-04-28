from urlparse import urlparse
from django.middleware.clickjacking import XFrameOptionsMiddleware
from sc_frontend import settings

__author__ = 'Joern'

class SCXFrameOptionsMiddleware(XFrameOptionsMiddleware):
    def get_xframe_options_value(self, request, response):
        try:
            referer = request.META['HTTP_REFERER']
            netloc = urlparse(referer).netloc
            if netloc in settings.XFRAME_EXEMPT_HOSTS:
                return 'ALLOWALL'  # non standard, equivalent to omitting
        except KeyError:
            pass
        return getattr(settings, 'X_FRAME_OPTIONS', 'SAMEORIGIN').upper()