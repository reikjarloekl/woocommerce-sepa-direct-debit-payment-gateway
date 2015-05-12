import os

__author__ = 'Joern'

BASE_DIR = os.path.dirname(__file__)

DATABASE_URL = 'sqlite:///' + os.path.join(os.path.dirname(BASE_DIR), 'sc_frontend/db.sqlite3')

