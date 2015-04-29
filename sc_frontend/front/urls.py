from django.conf.urls import url
from . import views

__author__ = 'Joern'

urlpatterns = [
    url(r'^$', views.index, name='index'),
    url(r'^(?P<camera_id>[0-9]+)/latest_image/$', views.latest_image, name='latest_image'),
]