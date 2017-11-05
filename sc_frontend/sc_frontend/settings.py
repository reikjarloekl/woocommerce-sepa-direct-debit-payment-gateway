"""
Django settings for sc_frontend project.

For more information on this file, see
https://docs.djangoproject.com/en/1.7/topics/settings/

For the full list of settings and their values, see
https://docs.djangoproject.com/en/1.7/ref/settings/
"""

# Build paths inside the project like this: os.path.join(BASE_DIR, ...)
import os
from django.contrib import messages

BASE_DIR = os.path.dirname(os.path.dirname(__file__))


# Quick-start development settings - unsuitable for production
# See https://docs.djangoproject.com/en/1.7/howto/deployment/checklist/

# SECURITY WARNING: keep the secret key used in production secret!
# Secret key used to calculate the password from the username
SMTP_SECRET_KEY = 'To2PqIc8jd2X9MN0pnu1Ug2mcFhm3vs05qHQo1k8zArWU18Cg5vR3sUOw6sv'
SECRET_KEY = '+xk8345@33(p)mx)me9vab$x98(y$-ok89ab+1wa--n=5zj1_^'
JWT_AUTH_KEY = 'R[=F):PwK@T%.[Sm%dsp2jralpUK^2;vcOmzl_QZ*#o-H:-q4bkj?&Qyp(1[$ul-'

# SECURITY WARNING: don't run with debug turned on in production!
DEBUG = False

TEMPLATE_DEBUG = False

ALLOWED_HOSTS = ['app.simplecam.de']

XFRAME_EXEMPT_HOSTS = ['www.simplecam.de', 'app.simplecam.de']

# Application definition

INSTALLED_APPS = (
    'django.contrib.admin',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    'django_ajax',
    'front',
)

MIDDLEWARE_CLASSES = (
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.auth.middleware.SessionAuthenticationMiddleware',
    'sc_frontend.jwt_authentication.jwt_authentication_middleware.JwtAuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'sc_frontend.sc_xframe_option_middleware.sc_xframe_option_middleware.SCXFrameOptionsMiddleware',
)

AUTHENTICATION_BACKENDS = (
    'django.contrib.auth.backends.ModelBackend',
    'sc_frontend.jwt_authentication.jwt_authentication_backend.JwtBackend',
)

ROOT_URLCONF = 'sc_frontend.urls'

WSGI_APPLICATION = 'sc_frontend.wsgi.application'


# Database
# https://docs.djangoproject.com/en/1.7/ref/settings/#databases

DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.mysql',
        'NAME': 'simplecam',
        'HOST': '127.0.0.1',
        'PORT': '3306',
        'USER': 'sc_front',
        'PASSWORD': 'DuLcBrq01NIveYWYTw8D',
    }
}

# Internationalization
# https://docs.djangoproject.com/en/1.7/topics/i18n/

LANGUAGE_CODE = 'de-de'

TIME_ZONE = 'Europe/Berlin'

USE_I18N = True

USE_L10N = True

USE_TZ = True

# Static files (CSS, JavaScript, Images)
# https://docs.djangoproject.com/en/1.7/howto/static-files/

STATIC_URL = '/static/'
STATIC_ROOT = '/var/www/simplecam.de/static'

# Directory where the received images are stored.
IMAGE_DIR = '/var/opt/simplecam/images'

# Confirmation Mail settings
CONFIRMATION_MAIL_SUBJECT = 'Einladung zu SimpleCam {}'
CONFIRMATION_MAIL_SENDER = 'info@simplecam.de'
CONFIRMATION_MAIL_URL = 'http://www.simplecam.de/app/confirm_email/{}'

# Email settings
EMAIL_HOST = 'wp228.webpack.hosteurope.de'
EMAIL_HOST_USER = 'wp1089149-info'
EMAIL_HOST_PASSWORD = 'wS94piCr4jwFntUkKrB0'

MESSAGE_TAGS = {
    messages.ERROR: 'danger'
}

TEMPLATE_DIRS = (
    os.path.join(BASE_DIR,  'templates'),
)
