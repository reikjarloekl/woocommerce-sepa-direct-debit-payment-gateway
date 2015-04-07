from django.shortcuts import render
from django.http import HttpResponse
from django.views.decorators.clickjacking import xframe_options_exempt

@xframe_options_exempt
def print_cookies(request):
   return HttpResponse(request.COOKIES)
