from django.conf.urls import patterns, include, url
from django.contrib import admin
from front.views import print_cookies, dummy


urlpatterns = patterns('',
    # Examples:
    # url(r'^$', 'sc_frontend.views.home', name='home'),
    # url(r'^blog/', include('blog.urls')),
    url(r'^test/$', print_cookies),
    url(r'^another_django_url/$', dummy),
    url(r'^admin/', include(admin.site.urls)),
)
