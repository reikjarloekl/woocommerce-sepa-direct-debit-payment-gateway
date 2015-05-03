from django.conf.urls import url
from . import views

__author__ = 'Joern'

urlpatterns = [
    url(r'^$', views.index, name='index'),
    url(r'^(?P<camera_id>[0-9]+)/latest_image/$', views.latest_image, name='latest_image'),
    url(r'^(?P<camera_id>[0-9]+)/delete_mail_forward/(?P<address_id>[0-9]+)$', views.delete_mail_forward,
        name='delete_mail_forward'),
]