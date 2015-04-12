from settings import *

__author__ = 'Joern'

DEBUG = True

TEMPLATE_DEBUG = True

ALLOWED_HOSTS.append('127.0.0.1')
STATIC_ROOT = 'c:\\temp\\static\\'

DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.sqlite3',
        'NAME': os.path.join(BASE_DIR, 'db.sqlite3'),
    }
}

