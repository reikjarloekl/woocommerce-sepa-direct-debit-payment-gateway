from sc_frontend import settings

__author__ = 'Joern'

from django.contrib.auth import authenticate, login
import jwt

class JwtAuthenticationMiddleware(object):
    """Authentication Middleware using a JWT cookie with a token.
    Backend will get user.
    """
    def process_request(self, request):
        if not hasattr(request, 'user'):
            raise ImproperlyConfigured()
        if "simplecam_jwt" not in request.COOKIES:
            return
        if request.user.is_authenticated():
            return
        token = request.COOKIES["simplecam_jwt"]
        try:
            payload = jwt.decode(token, settings.JWT_AUTH_KEY, algorithms=['HS256'])
        except jwt.DecodeError:
            return

        user = authenticate(**payload)
        if user is None:
            return
        request.user = user
        login(request, user)